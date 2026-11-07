<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'ip_address',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Write a single audit row. Never include passwords in $changes.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $changes = [],
        ?Request $request = null,
    ): self {
        $req = $request ?? app(Request::class);

        return static::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes ?: null,
            'ip_address' => $req->ip(),
            'created_at' => now(),
        ]);
    }
}
