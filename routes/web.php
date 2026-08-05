<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Define una ruta GET para la raíz del sitio web ('/')
Route::get('/', function () {
// Devuelve la vista 'welcome' cuando se accede a la raíz del sitio
    return view('welcome');
});
