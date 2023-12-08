<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SecurityQuestion;
class SecurityQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SecurityQuestion::truncate();

        $securityQuestions = [
            ['question' => 'What is your mother\'s maiden name?'],
            ['question' => 'What was the name of your first pet?'],
            ['question' => 'In what city were you born?'],
            ['question' => 'What is your favorite color?'],
            ['question' => 'What was the make and model of your first car?'],
            ['question' => 'What is the name of your favorite teacher?'],
            ['question' => 'What is your favorite book?'],
            ['question' => 'What is the name of the street you grew up on?'],
            ['question' => 'What is your favorite movie?'],
            ['question' => 'What is the name of your best childhood friend?'],
            ['question' => 'What was the first concert you attended?'],
            ['question' => 'What is the name of your favorite sports team?'],
            ['question' => 'What is the name of the hospital where you were born?'],
            ['question' => 'What is your favorite holiday destination?'],
            ['question' => 'What is the name of your favorite fictional character?'],
            ['question' => 'What is the first name of your oldest cousin?'],
            ['question' => 'What is the name of your favorite restaurant?'],
            ['question' => 'What is the last name of your favorite high school teacher?'],
            ['question' => 'What is the name of the company where you had your first job?'],
            ['question' => 'What is the name of your favorite childhood toy?']
        ];

        foreach ($securityQuestions as $question) {
            SecurityQuestion::create($question);
        }
    }
}
