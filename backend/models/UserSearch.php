<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use backend\models\User;
use yii\data\Sort;

/**
 * UserSearch represents the model behind the search form of `backend\models\User`.
 */
class UserSearch extends User
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'status', 'substatus', 'sequential_number', 'current_project_id', 'administrator_id', 'total_time_today'], 'integer'],
            [[ 'email', 'verification_token', 'first_name', 'last_name',
                'address', 'phone_number', 'city', 'state', 'zip_code', 'position_title', 'country', 'home_phone', 'hr_source'], 'safe'],
            [['is_online'], 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param string|null $formName Form name to be used into `->load()` method.
     *
     * @return ActiveDataProvider
     */
    public function search($params, $formName = null)
    {
        $query = User::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => $this->createSortConfiguration(),
        ]);


        $this->load($params, $formName);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'user.id' => $this->id,
            'user.status' => $this->status,
            'user.substatus' => $this->substatus,
            'user.created_at' => $this->created_at,
            'user.updated_at' => $this->updated_at,
            'user.sequential_number' => $this->sequential_number,
            'user.current_project_id' => $this->current_project_id,
            'user.administrator_id' => $this->administrator_id,
            'user.total_time_today' => $this->total_time_today,
            'user.is_online' => $this->is_online,
        ]);

        $query->andFilterWhere(['like', 'user.auth_key', $this->auth_key])
        ->andFilterWhere(['like', 'user.email', $this->email])
        ->andFilterWhere(['like', 'user.first_name', $this->first_name])
        ->andFilterWhere(['like', 'user.last_name', $this->last_name])
        ->andFilterWhere(['like', 'user.address', $this->address])
        ->andFilterWhere(['like', 'user.phone_number', $this->phone_number])
        ->andFilterWhere(['like', 'user.city', $this->city])
        ->andFilterWhere(['like', 'user.state', $this->state])
        ->andFilterWhere(['like', 'user.zip_code', $this->zip_code])
        ->andFilterWhere(['like', 'user.position_title', $this->position_title])
        ->andFilterWhere(['like', 'user.country', $this->country])
        ->andFilterWhere(['like', 'user.home_phone', $this->home_phone])
        ->andFilterWhere(['like', 'user.hr_source', $this->hr_source]);

        return $dataProvider;
    }

    private function createSortConfiguration(): Sort
    {
        return new Sort([
            'attributes' => [
                'id' => [
                    'asc' => ['user.id' => SORT_ASC],
                    'desc' => ['user.id' => SORT_DESC],
                ],
                'sequential_number' => [
                    'asc' => ['user.sequential_number' => SORT_ASC],
                    'desc' => ['user.sequential_number' => SORT_DESC],
                ],
                'first_name' => [
                    'asc' => ['user.first_name' => SORT_ASC],
                    'desc' => ['user.first_name' => SORT_DESC],
                ],
                'last_name' => [
                    'asc' => ['user.last_name' => SORT_ASC],
                    'desc' => ['user.last_name' => SORT_DESC],
                ],
                'email' => [
                    'asc' => ['user.email' => SORT_ASC],
                    'desc' => ['user.email' => SORT_DESC],
                ],
                'phone_number' => [
                    'asc' => ['user.phone_number' => SORT_ASC],
                    'desc' => ['user.phone_number' => SORT_DESC],
                ],
                'home_phone' => [
                    'asc' => ['user.home_phone' => SORT_ASC],
                    'desc' => ['user.home_phone' => SORT_DESC],
                ],
                'address' => [
                    'asc' => ['user.address' => SORT_ASC],
                    'desc' => ['user.address' => SORT_DESC],
                ],
                'city' => [
                    'asc' => ['user.city' => SORT_ASC],
                    'desc' => ['user.city' => SORT_DESC],
                ],
                'state' => [
                    'asc' => ['user.state' => SORT_ASC],
                    'desc' => ['user.state' => SORT_DESC],
                ],
                'country' => [
                    'asc' => ['user.country' => SORT_ASC],
                    'desc' => ['user.country' => SORT_DESC],
                ],
                'zip_code' => [
                    'asc' => ['user.zip_code' => SORT_ASC],
                    'desc' => ['user.zip_code' => SORT_DESC],
                ],
                'position_title' => [
                    'asc' => ['user.position_title' => SORT_ASC],
                    'desc' => ['user.position_title' => SORT_DESC],
                ],
                'hr_source' => [
                    'asc' => ['user.hr_source' => SORT_ASC],
                    'desc' => ['user.hr_source' => SORT_DESC],
                ],
                'administrator_id' => [
                    'asc' => ['user.administrator_id' => SORT_ASC],
                    'desc' => ['user.administrator_id' => SORT_DESC],
                ],
                'current_project_id' => [
                    'asc' => ['user.current_project_id' => SORT_ASC],
                    'desc' => ['user.current_project_id' => SORT_DESC],
                ],
                'status' => [
                    'asc' => ['user.status' => SORT_ASC],
                    'desc' => ['user.status' => SORT_DESC],
                ],
                'substatus' => [
                    'asc' => ['user.substatus' => SORT_ASC],
                    'desc' => ['user.substatus' => SORT_DESC],
                ],
                'is_online' => [
                    'asc' => ['user.is_online' => SORT_ASC],
                    'desc' => ['user.is_online' => SORT_DESC],
                ],
                'total_time_today' => [
                    'asc' => ['user.total_time_today' => SORT_ASC],
                    'desc' => ['user.total_time_today' => SORT_DESC],
                ],
                'created_at' => [
                    'asc' => ['user.created_at' => SORT_ASC],
                    'desc' => ['user.created_at' => SORT_DESC],
                ],
                'updated_at' => [
                    'asc' => ['user.updated_at' => SORT_ASC],
                    'desc' => ['user.updated_at' => SORT_DESC],
                ],
            ],
            'defaultOrder' => [
                'created_at' => SORT_DESC
            ]
        ]);
    }


}
