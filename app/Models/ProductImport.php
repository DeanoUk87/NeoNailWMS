<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fulfilment_client_id
 * @property int $imported_by
 * @property string $import_type   products|variant_mappings
 * @property string $original_filename
 * @property string $stored_filename
 * @property array $column_mapping
 * @property string $status        preview|validated|confirmed|failed|partial
 * @property int $total_rows
 * @property int $imported_rows
 * @property int $skipped_rows
 * @property int $error_rows
 * @property array|null $validation_report
 * @property \Carbon\Carbon|null $confirmed_at
 */
class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_client_id',
        'imported_by',
        'import_type',
        'original_filename',
        'stored_filename',
        'column_mapping',
        'status',
        'total_rows',
        'imported_rows',
        'skipped_rows',
        'error_rows',
        'validation_report',
        'confirmed_at',
    ];

    protected $casts = [
        'column_mapping'    => 'array',
        'validation_report' => 'array',
        'confirmed_at'      => 'datetime',
    ];

    public function fulfilmentClient(): BelongsTo
    {
        return $this->belongsTo(FulfilmentClient::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function hasErrors(): bool
    {
        return $this->error_rows > 0;
    }

    public function summaryLine(): string
    {
        return "{$this->imported_rows} imported, {$this->skipped_rows} skipped, {$this->error_rows} errors of {$this->total_rows} total rows";
    }
}
