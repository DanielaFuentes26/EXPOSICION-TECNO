<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retraso simulado al generar facturas
    |--------------------------------------------------------------------------
    |
    | Segundos que "tarda" GenerateInvoice. Sirve para mostrar en la exposición
    | que el usuario recibe respuesta inmediata aunque el job sea lento.
    |
    */

    'invoice_delay_seconds' => (int) env('INVOICE_DELAY_SECONDS', 3),

];
