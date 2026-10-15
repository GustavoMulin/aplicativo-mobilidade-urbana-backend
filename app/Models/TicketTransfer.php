<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransfer extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'ticket_id', 'transferred_by_user_id', 'from_user_id', 'to_user_id',
        'transferred_by_name', 'from_user_name', 'to_user_name', 'type', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChamadoAjuda::class, 'ticket_id');
    }
}
