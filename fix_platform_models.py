import pathlib, re

models_path = pathlib.Path('/srv/http/ecommerce-blog-back/app/Models')

# These models belongs to the PLATFORM DB (already have connection or correct)
platform_models = [
    'Employment.php',
    'StaffSpotlight.php',
    'Team.php',
    'TeamMember.php',
    'User.php',
    'UserProfile.php',
]

fixed = []
skipped = []

for filename in platform_models:
    p = models_path / filename
    if not p.exists():
        print(f" NOT FOUND: {filename}")
        continue
    
    src = p.read_text()

    # Skip if already has connection property
    if "'mercatura'" in src or '"mercatura"' in src:
        skipped.append(filename)
        print(f" -> {filename} (already set)")
        continue
    
    # Add connection after class declaration
    src = re.sub(
        r'(class \w+ extends (?:Model|Authenticatable)\s*\{)',
        r"\1\n    protected $connection = 'mercatura';\n",
        src
    )

    p.write_text(src)
    fixed.append(filename)
    print(f" {filename}")

print(f"\n Fixed {len(fixed)} platform models")
print(f"-> Skipped {len(skipped)} (already set)")








