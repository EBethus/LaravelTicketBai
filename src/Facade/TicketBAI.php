<?php
namespace EBethus\LaravelTicketBAI\Facade;

class TicketBAI
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \EBethus\LaravelTicketBAI::class;
    }
}