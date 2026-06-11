<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Karyawan extends Model
{
    public const EMPLOYMENT_FULL_TIME = 'full_time';
    public const EMPLOYMENT_PART_TIME = 'part_time';

    protected $table = 'karyawan';
    protected $primaryKey = 'id_karyawan';
    public $timestamps = false;

    protected $fillable = [
        'nama_karyawan',
        'jabatan',
        'no_telepon',
        'alamat',
        'foto_profil_path',
        'employment_type',
        'monthly_salary',
        'hourly_rate',
        'pin_digest',
        'pin_encrypted',
        'is_active',
    ];

    protected $casts = [
        'employment_type' => 'string',
        'monthly_salary' => 'integer',
        'hourly_rate' => 'integer',
        'is_active' => 'boolean',
    ];

    public function profilePhotoUrl(): ?string
    {
        $path = trim((string) ($this->foto_profil_path ?? ''));

        if ($path === '') {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        $url = '/storage/' . $normalizedPath;

        if ($disk->exists($path)) {
            $version = $disk->lastModified($path);
            return $version ? ($url . '?v=' . $version) : $url;
        }

        return $url;
    }

    public static function employmentTypeOptions(): array
    {
        return [
            self::EMPLOYMENT_FULL_TIME => [
                'label' => 'Full Time',
                'short' => 'FT',
                'duration_minutes' => 480,
                'duration_label' => '8 jam',
            ],
            self::EMPLOYMENT_PART_TIME => [
                'label' => 'Part Time',
                'short' => 'PT',
                'duration_minutes' => 270,
                'duration_label' => '4,5 jam',
            ],
        ];
    }

    public static function normalizeEmploymentType(?string $type): string
    {
        $normalized = trim((string) $type);

        return array_key_exists($normalized, static::employmentTypeOptions())
            ? $normalized
            : self::EMPLOYMENT_FULL_TIME;
    }

    public static function employmentTypeLabelFor(?string $type): string
    {
        $key = static::normalizeEmploymentType($type);

        return (string) (static::employmentTypeOptions()[$key]['label'] ?? 'Full Time');
    }

    public static function employmentTypeShortLabelFor(?string $type): string
    {
        $key = static::normalizeEmploymentType($type);

        return (string) (static::employmentTypeOptions()[$key]['short'] ?? 'FT');
    }

    public static function employmentDurationMinutesFor(?string $type): int
    {
        $key = static::normalizeEmploymentType($type);

        return (int) (static::employmentTypeOptions()[$key]['duration_minutes'] ?? 480);
    }

    public static function employmentDurationLabelFor(?string $type): string
    {
        $key = static::normalizeEmploymentType($type);

        return (string) (static::employmentTypeOptions()[$key]['duration_label'] ?? '8 jam');
    }

    public function employmentTypeValue(): string
    {
        return static::normalizeEmploymentType($this->employment_type ?? null);
    }

    public function employmentTypeLabel(): string
    {
        return static::employmentTypeLabelFor($this->employmentTypeValue());
    }

    public function employmentTypeShortLabel(): string
    {
        return static::employmentTypeShortLabelFor($this->employmentTypeValue());
    }

    public function employmentDurationMinutes(): int
    {
        return static::employmentDurationMinutesFor($this->employmentTypeValue());
    }

    public function employmentDurationLabel(): string
    {
        return static::employmentDurationLabelFor($this->employmentTypeValue());
    }

    public function employmentSummaryLabel(): string
    {
        return $this->employmentTypeLabel() . ' - ' . $this->employmentDurationLabel();
    }

    public function salaryScheme(): string
    {
        return $this->employmentTypeValue() === self::EMPLOYMENT_PART_TIME
            ? 'hourly'
            : 'monthly';
    }

    public function salarySchemeLabel(): string
    {
        return $this->salaryScheme() === 'hourly'
            ? 'Per Jam'
            : 'Bulanan';
    }

    public function baseSalaryAmount(): int
    {
        return $this->salaryScheme() === 'hourly'
            ? (int) ($this->hourly_rate ?? 0)
            : (int) ($this->monthly_salary ?? 0);
    }

    public function baseSalaryLabel(): string
    {
        $amount = number_format($this->baseSalaryAmount(), 0, ',', '.');

        return $this->salaryScheme() === 'hourly'
            ? 'Rp ' . $amount . ' / jam'
            : 'Rp ' . $amount . ' / bulan';
    }

    public static function pinDigest(string $pin): string
    {
        $key = (string) config('app.key', '');
        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                $key = $decoded;
            }
        }

        return hash_hmac('sha256', trim($pin), $key ?: 'kopisop');
    }

    public function pesanan()
    {
        return $this->hasMany(Pesanan::class, 'id_karyawan', 'id_karyawan');
    }
}
