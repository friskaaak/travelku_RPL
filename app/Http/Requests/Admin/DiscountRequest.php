<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));
        $this->merge(['code' => $code === '' ? null : $code]);
    }

    public function rules(): array
    {
        $id = $this->route('discount')?->id;
        $automatic = $this->boolean('is_automatic');
        $max = $this->input('type') === 'percentage' ? 'max:100' : 'max:100000000';

        return [
            'name' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', $max],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'min_purchase' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'is_automatic' => ['nullable', 'boolean'],
            'code' => [
                Rule::requiredIf(! $automatic), 'nullable', 'string', 'max:30',
                'regex:/^[A-Z0-9\-]+$/', Rule::unique('discounts', 'code')->ignore($id),
            ],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', Rule::in(['hotel', 'flight', 'train'])],
            'flight_scope' => ['nullable', Rule::in(['domestic', 'international'])],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_stackable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->boolean('is_automatic') && ($this->filled('usage_limit') || $this->filled('per_user_limit'))) {
                $v->errors()->add('usage_limit', 'Diskon otomatis tidak mendukung batas pemakaian. Gunakan kode promo bila Anda memerlukan kuota.');
            }

            $discount = $this->route('discount');
            if ($discount && $this->filled('usage_limit') && (int) $this->input('usage_limit') < (int) $discount->used_count) {
                $v->errors()->add('usage_limit', "Kuota tidak boleh di bawah pemakaian saat ini ({$discount->used_count}).");
            }

            // Diskon baru tidak boleh langsung berakhir di masa lalu.
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
            'name.required' => 'Nama diskon wajib diisi.',
            'name.max' => 'Nama diskon maksimal 120 karakter.',
            'label.max' => 'Label maksimal 40 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
            'terms.max' => 'Syarat dan ketentuan maksimal 1000 karakter.',
            'type.required' => 'Jenis potongan wajib dipilih.',
            'type.in' => 'Jenis potongan tidak valid.',
            'value.required' => 'Nilai potongan wajib diisi.',
            'value.integer' => 'Nilai potongan harus berupa angka bulat.',
            'value.min' => 'Nilai potongan minimal 1.',
            'value.max' => 'Nilai potongan melebihi batas (persen maksimal 100, nominal maksimal Rp100.000.000).',
            'max_discount.integer' => 'Batas potongan harus berupa angka bulat.',
            'min_purchase.integer' => 'Minimal transaksi harus berupa angka bulat.',
            'min_purchase.min' => 'Minimal transaksi tidak boleh negatif.',
            'code.required' => 'Kode promo wajib diisi untuk diskon non-otomatis.',
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, dan tanda hubung.',
            'code.unique' => 'Kode promo ini sudah dipakai diskon lain.',
            'code.max' => 'Kode maksimal 30 karakter.',
            'services.required' => 'Pilih minimal satu layanan.',
            'services.min' => 'Pilih minimal satu layanan.',
            'services.*.in' => 'Layanan tidak valid.',
            'flight_scope.in' => 'Cakupan penerbangan tidak valid.',
            'starts_at.required' => 'Tanggal mulai wajib diisi.',
            'starts_at.date_format' => 'Format tanggal mulai tidak valid.',
            'ends_at.required' => 'Tanggal berakhir wajib diisi.',
            'ends_at.date_format' => 'Format tanggal berakhir tidak valid.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
            'usage_limit.integer' => 'Kuota harus berupa angka bulat.',
            'usage_limit.min' => 'Kuota minimal 1.',
            'per_user_limit.integer' => 'Batas per pengguna harus berupa angka bulat.',
            'per_user_limit.min' => 'Batas per pengguna minimal 1.',
        ];
    }
}