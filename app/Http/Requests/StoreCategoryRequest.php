<?php
  namespace App\Http\Requests;

  use Illuminate\Foundation\Http\FormRequest;

  class StoreCategoryRequest extends FormRequest 
  { 
     public function authorize(): bool
     {
        return auth()->check();
     }
   
     public function rules(): array 
     {
        return [
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ];
     }
  }
