<?php

namespace Database\Seeders;

use App\Models\Footer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FooterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $footers = [
            [
                'facebook'=>'https://www.facebook.com/',
                'twitter'=>'https://twitter.com/',
                'instagram'=>'https://www.instagram.com/',
                'linkedin'=>'https://www.linkedin.com/',
                'youtube'=>'https://www.youtube.com/',
                'logo'=>'',
                'google_play'=>'',
                'app_store'=>'',
            ]
        ];

        foreach($footers as $footer){
            Footer::create($footer);
        }
    }
}
