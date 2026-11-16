<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('promo_code')));
        $this->merge(['promo_code' => $code === '' ? null : $code]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'badge' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:1000'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_banner' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', Rule::in(['hotel', 'flight', 'train'])],
            'min_purchase' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'promo_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', Rule::exists('discounts', 'code')],
            'discount_ids' => ['nullable', 'array', 'max:20'],
            'discount_ids.*' => ['integer', Rule::exists('discounts', 'id')],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->isMethod('post') && ! $v->errors()->has('ends_at') && $this->filled('ends_at')) {
                if (Carbon::createFromFormat('Y-m-d\TH:i', $this->input('ends_at'))->isPast()) {
                    $v->errors()->add('ends_at', 'Tanggal berakhir harus di masa depan.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kampanye wajib diisi.',
            'name.max' => 'Nama kampanye maksimal 120 karakter.',
            'badge.max' => 'Lencana maksimal 40 karakter.',
            'description.required' => 'Deskripsi wajib diisi.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'banner.image' => 'Banner harus berupa gambar.',
            'banner.mimes' => 'Banner harus berformat JPG, PNG, atau WebP.',
            'banner.max' => 'Ukuran banner maksimal 2 MB.',
            'starts_at.required' => 'Tanggal mulai wajib diisi.',
            'starts_at.date_format' => 'Format tanggal mulai tidak valid.',
            'ends_at.required' => 'Tanggal berakhir wajib diisi.',
            'ends_at.date_format' => 'Format tanggal berakhir tidak valid.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
            'services.required' => 'Pilih minimal satu layanan.',
            'services.min' => 'Pilih minimal satu layanan.',
            'services.*.in' => 'Layanan tidak valid.',
            'min_purchase.integer' => 'Minimal transaksi harus berupa angka bulat.',
            'min_purchase.min' => 'Minimal transaksi tidak boleh negatif.',
            'promo_code.regex' => 'Kode hanya boleh berisi huruf, angka, dan tanda hubung.',
            'promo_code.exists' => 'Kode promo tidak ditemukan di daftar diskon. Buat diskonnya lebih dulu.',
            'discount_ids.*.exists' => 'Ada diskon terkait yang tidak ditemukan.',
        ];
    }
}