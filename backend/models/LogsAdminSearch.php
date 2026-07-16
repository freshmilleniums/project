<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\LogsAdmin;

/**
 * LogsAdminSearch represents the model behind the search form of `common\models\LogsAdmin`.
 */
class LogsAdminSearch extends LogsAdmin
{
    public $user_name;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id'], 'integer'],
            [['action_type', 'section', 'user_name'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = LogsAdmin::find()->alias('log')->with('user');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['date' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'log.id' => $this->id,
            'log.user_id' => $this->user_id,
            'log.date' => $this->date,
        ]);

        $query->andFilterWhere(['like', 'log.action_type', $this->action_type])
            ->andFilterWhere(['like', 'log.section', $this->section]);

        if (!empty($this->user_name)) {
            $query->joinWith(['user' => function($q) {
                $q->andFilterWhere(['or',
                    ['like', 'first_name', $this->user_name],
                    ['like', 'last_name', $this->user_name],
                    ['like', 'email', $this->user_name],
                ]);
            }]);
        }

        return $dataProvider;
    }
}
