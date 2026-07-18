<?php

namespace App\Support;

/**
 * Catálogo de categorías por defecto (con íconos de Tabler) que se crean
 * para cada usuario nuevo. Replica el set de Money Manager.
 */
class DefaultCategories
{
    /** @return array<int, array{name:string, icon:string, color:string}> */
    public static function expense(): array
    {
        return [
            ['name' => 'Compras',         'icon' => 'shopping-cart',    'color' => '#e0a82e'],
            ['name' => 'Alimentos',       'icon' => 'tools-kitchen-2',  'color' => '#14b8a6'],
            ['name' => 'Teléfono',        'icon' => 'device-mobile',    'color' => '#ec4899'],
            ['name' => 'Entretenimiento', 'icon' => 'device-gamepad-2', 'color' => '#22c55e'],
            ['name' => 'Educación',       'icon' => 'school',           'color' => '#f97316'],
            ['name' => 'Belleza',         'icon' => 'scissors',         'color' => '#fb7185'],
            ['name' => 'Deportes',        'icon' => 'run',              'color' => '#a855f7'],
            ['name' => 'Social',          'icon' => 'users',            'color' => '#3b82f6'],
            ['name' => 'Transporte',      'icon' => 'bus',              'color' => '#b0a08f'],
            ['name' => 'Ropa',            'icon' => 'shirt',            'color' => '#38bdf8'],
            ['name' => 'Auto',            'icon' => 'car',              'color' => '#64748b'],
            ['name' => 'Bebidas',         'icon' => 'glass-full',       'color' => '#ef4444'],
            ['name' => 'Cigarrillos',     'icon' => 'smoking',          'color' => '#9ca3af'],
            ['name' => 'Electrónicos',    'icon' => 'device-desktop',   'color' => '#06b6d4'],
            ['name' => 'Viajes',          'icon' => 'plane',            'color' => '#0ea5e9'],
            ['name' => 'Salud',           'icon' => 'heartbeat',        'color' => '#f43f5e'],
            ['name' => 'Mascotas',        'icon' => 'paw',              'color' => '#d97706'],
            ['name' => 'Reparaciones',    'icon' => 'tool',             'color' => '#f59e0b'],
            ['name' => 'Vivienda',        'icon' => 'home',             'color' => '#10b981'],
            ['name' => 'Hogar',           'icon' => 'sofa',             'color' => '#8b5cf6'],
            ['name' => 'Regalos',         'icon' => 'gift',             'color' => '#ec4899'],
            ['name' => 'Donaciones',      'icon' => 'heart-handshake',  'color' => '#f43f5e'],
            ['name' => 'Lotería',         'icon' => 'dice',             'color' => '#22c55e'],
            ['name' => 'Snacks',          'icon' => 'candy',            'color' => '#eab308'],
            ['name' => 'Hijos',           'icon' => 'mood-kid',         'color' => '#60a5fa'],
            ['name' => 'Verduras',        'icon' => 'carrot',           'color' => '#f97316'],
            ['name' => 'Frutas',          'icon' => 'cherry',           'color' => '#ef4444'],
        ];
    }

    /** @return array<int, array{name:string, icon:string, color:string}> */
    public static function income(): array
    {
        return [
            ['name' => 'Sueldo',       'icon' => 'cash',           'color' => '#22c55e'],
            ['name' => 'Bonos',        'icon' => 'gift',           'color' => '#eab308'],
            ['name' => 'Inversiones',  'icon' => 'trending-up',    'color' => '#10b981'],
            ['name' => 'Intereses',    'icon' => 'percentage',     'color' => '#14b8a6'],
            ['name' => 'Ventas',       'icon' => 'building-store', 'color' => '#3b82f6'],
            ['name' => 'Otros',        'icon' => 'coin',           'color' => '#a3a3a3'],
        ];
    }
}
