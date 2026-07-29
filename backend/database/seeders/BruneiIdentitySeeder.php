<?php

namespace Database\Seeders;

use App\Models\BruneiIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BruneiIdentitySeeder extends Seeder
{
    public function run(): void
    {
        BruneiIdentity::updateOrCreate(
            ['ic_no' => '00-234567'],
            [
                'full_name' => 'Test User',
                'address' => 'Brunei Darussalam',
                'nationality' => 'Bruneian',
                'id_picture' => null,
                'password' => Hash::make('123456'),
            ]
        );
    }
}