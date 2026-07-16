<?php

use yii\db\Migration;

class m260501_174655_seed_training_modules extends Migration
{
    public function safeUp()
    {
        $time = time();

        $modules = [
            ['title' => 'Welcome and Role Introduction', 'sort' => 1, 'passing_score' => 80],
            ['title' => 'Work Discipline', 'sort' => 2, 'passing_score' => 80],
            ['title' => 'ICO vs IPO', 'sort' => 3, 'passing_score' => 80],
            ['title' => 'Business Angels', 'sort' => 4, 'passing_score' => 80],
            ['title' => 'Venture Capital', 'sort' => 5, 'passing_score' => 80],
            ['title' => 'Investor Communication', 'sort' => 6, 'passing_score' => 80],
            ['title' => 'Investment Proposal Letters', 'sort' => 7, 'passing_score' => 80],
            ['title' => 'Compliance and Ethics', 'sort' => 8, 'passing_score' => 80],
            ['title' => 'Final Task', 'sort' => 9, 'passing_score' => 80],
        ];

        foreach ($modules as $module) {
            $this->insert('{{%training_modules}}', [
                'title' => $module['title'],
                'content' => null,
                'sort' => $module['sort'],
                'is_active' => 1,
                'passing_score' => $module['passing_score'],
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }
    }

    public function safeDown()
    {
        $this->truncateTable('{{%training_modules}}');
    }
}
