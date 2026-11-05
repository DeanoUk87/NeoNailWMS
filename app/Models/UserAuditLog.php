<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'user_audit_log';

    protected $fillable = [
        'target_user_id',
        'performed_by',
        'action',
        'changes',
        'notes',
        'performed_at',
    ];

    protected $casts = [
        'changes'      => 'array',
        'performed_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Record an audit entry.
     */
    public static function record(
        User $target,
        User $actor,
        string $action,
        array $changes = [],
        string $notes = ''
    ): static {
        return static::create([
            'target_user_id' => $target->id,
            'performed_by'   => $actor->id,
            'action'         => $action,
            'changes'        => $changes ?: null,
            'notes'          => $notes ?: null,
            'performed_at'   => now(),
        ]);
    }
}
