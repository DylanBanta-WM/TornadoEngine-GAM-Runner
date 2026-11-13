<?php

namespace TornadoEngine\GamRunner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Run extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gam_runner_history';

    protected $fillable = ['user_id', 'command', 'exit', 'error', 'seconds'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
