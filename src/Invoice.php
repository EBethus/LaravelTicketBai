<?php
namespace EBethus\LaravelTicketBAI;

use Illuminate\Database\Eloquent\Model;


class Invoice extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'json',
    ];
}