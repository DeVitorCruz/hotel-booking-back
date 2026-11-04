<?php

namespace App\Services\Tenant;
use App\Models\TenantApp;
use App\Models\PlatformUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

/**
 * Orchestrates everything needed to bring a new TenantApp to life.
 * 
 * Called by AppController::store after the TenantApp record is persisted.
 * The TenantApp starts with status = 'provisioning'; this service either 
 * advances it to 'active' or marks it 'failed' and re-throws the exception.
 * 
 * Steps
 * 
 * 1. CREATE DATABASE - root PDO, plus GRANT to the app DB user
 * 2. migrate:tenant - runs database/migrations/tenant/* against the new DB
 * 3. Seed permissions - workspace + e-commerce roles (RolesAndPermissionsSeeder)
 * 4. Owner user - mirrors the PlatformUser into the tenant DB
 * 5. Owner role - assigns tenant-owner (web guard) to the user
 */
class TenantProvisioningService
{
    // Public API

    /**
     * Fully provision a TenantApp and mark it active on success.
     * 
     * @param TenantApp $tenantApp The app record (status = 'provisioning')
     * @param PlatformUser $platformUser The platform user who owns the tenant
     * @throws \Throwable                Re-throw after marking the app 'failed'
     */
    public function provision(TenantApp $tenantApp, PlatformUser $platformUser): void 
    {
        $dbName = $tenantApp->db_name;

        try {
            $this->createDatabase($dbName);
            $this->runMigrations($dbName);
            $this->seedPermissions($dbName);
            $tenantUser = $this->createOwnerUser($dbName, $platformUser);
            $this->assignOwnerRole($tenantUser, $dbName);

            $tenantApp->update(['status' => 'active']);

            Log::info("TenantProvisioningService: [{$tenantApp->slug}] -> [{$dbName}] - active");
        } catch (\Throwable $e) {
            $tenantApp->update(['status' => 'failed']);
            Log::error("TenantProvisioningService: provisioning failed for [{$dbName}]: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Drop the tenant database.
     * Safe to call even if the DB does not exist (uses DROP IF EXISTS).
     * Typically used when cleaning up a failed provisioning or on app deletion.
     * 
     * @param TenantApp $tenantApp, Tenant app to be deprovisioned
     * @throws \Throwable 
     */
    public function deprovision(TenantApp $tenantApp): void 
    {
        $dbName = $tenantApp->db_name;
        
        try {
            this->rootPdo()->exec("DROP DATABASE IF EXISTS `{$dbName}`");
            Log::info("TenantProvisioningService: dropped [{$dbName}]");
        } catch (\Throwable $e) {
            Log::error("TenantProvisioningService: deprovision failed for [{$dbName}]: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Step 1 - Create the database and grant the app DB user full access.
     * 
     * The DB username is taken from the mercatura connection config so there's
     * no hardcoded value here - change config/database.php, not this service.
     * 
     * @param string $dbName, database name to be created
     */
    private function createDatabase(string $dbName): void 
    {
        $pdo = $this->rootPdo();
        $user = config('database.connections.mercatura.username');

        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$user}'@'%'");
        $pdo->exec("FLUSH PRIVILEGES");
    }

    /**
     * Step 2 - Run the tenant migrations against the new database.
     * 
     * Delegates to the existing migrate:tenant Artisan command which already
     * handles pointing the tenant connection at the right DB and running
     * database/migrations/tenant/*.
     * 
     * @param string $dbName the name to the database to be ran
     */
    private function runMigrations(string $dbName): void 
    {
        $exitCode = Artisan::call('migrate:tenant', ['db' => $dbName]);

        if ($exitCode !== 0) {
            throw new \RuntimeExeption(
                "migrate:tenant failed for [{$dbName}] (exit {$exitCode})." . Artisan::output()
            );
        }
    }

    /**
     * Step 3 - Seed workspace and e-commerce roles/permissions.
     * 
     * Points the tenant connection at the new DB before calling the seeder.
     * The seeder itself also database.default = 'tenant' and restores it,
     * so the two are redundant but safe - belt and braces.
     * 
     * @param string $dbName, db to seed permissions
     */
    private function seedPermissions(string $dbName): void 
    {
        $this->switchTenantConnection($dbName);

        Artisan::call('db:seed', [
            '--class' => 'RolesAndPermissionsSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Step 4 - Create the tenant-owner user in the new DB.
     * 
     * Mirrors the PlatformUser's email, name and (already-hashed) password
     * so the tenant owner can log in to their app with the same credentials
     * they used on the platform.
     * 
     * firstOrCreate is intential: if provisioning is retried after a
     * partial failure the user won't be duplicated.
     * 
     * @param string $dbName, tenant db
     * @param PlatformUser $platformUser, platform to user to be turn into tenant-owner 
     * @return User tenant-owner user returned
     */
    private function createOwnerUser(string $dbName, PlatformUser $platformUser): User
    {
        $this->switchTenantConnection($dbName);

        return User::on('tenant')->firstOrCreate(
            ['email' => $platformUser->email],
            [
                'name' => $platformUser->name,
                'password' => $platformUser->password, // already hashed - no Hash::make()
            ]
        );
    }

    /**
     * Step 5 - Assign the tenant-owner role (web guard) to the new user.
     * 
     * Loads the role directly from the tenant DB instead of relying on
     * Spatie's cache, which may still point at a different connection.
     * 
     * @param User $tenantUser, user tenant to be assigned as owner
     * @param string $dbName, dbName to be pointed at tenant connection
     */
    private function assignOwnerRole(User $tenantUser, string $dbName): void 
    {
        $this->switchTenantConnection($dbName);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::on('tenant')
            ->where('name', 'tenant-owner')
            ->where('guard_name', 'web')
            ->firstOrFail();
        
        $tenantUser->assignRole($role);
    }

    // Helpers

    /**
     * Point the tenant DB connection at $dbName and purge Laravel's
     * connection cache so the very next query hits the correct database.
     * 
     * Must be called before every step that touches the tenant DB because
     * previous steps (or previous requests) may have left the connection
     * pointing at a different tenant's database.
     * 
     * @param string $dbName database name to be pointed
     */
    private function switchTenantConnection(string $dbName): void 
    {
        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Open a PDO connection as the MySQL root user.
     * 
     * Root credentials are needed for CREATE DATABASE and GRANT - the app
     * DB user normally cannot do these. The password is taken from the DB_ROOT_PASSWORD 
     * env variable (defaults to 'root' for local dev).
     *  
     * PDO::ERRMODE_EXCEPTION is set so any SQL error throws immediately
     * rather than silently failing.
     */
    private function rootPdo(): \PDO 
    {
        $host = config('database.connections.mercatura.host', '127.0.0.1');
        $port = config('database.connections.mercatura.port', '3306');

        return new \PDO(
            "mysql:host={$host};port={$port}",
            'root',
            env('DB_ROOT_PASSWORD', 'root'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
