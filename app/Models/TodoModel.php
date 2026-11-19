<?php

namespace App\Models;

use CodeIgniter\Model;

class TodoModel extends Model
{
    protected $table         = 'todos';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['title', 'notes', 'customer_id', 'due_date', 'due_time', 'duration', 'done', 'done_at', 'created_by'];

    /** Open taken met klantnaam: te laat en vandaag eerst, daarna op datum, zonder datum achteraan */
    public function open(?int $customerId = null, ?int $limit = null): array
    {
        $b = $this->select('todos.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = todos.customer_id', 'left')
            ->where('todos.done', 0);
        if ($customerId !== null) {
            $b->where('todos.customer_id', $customerId);
        }
        $b->orderBy('todos.due_date IS NULL', '', false)->orderBy('todos.due_date')->orderBy('todos.due_time IS NULL', '', false)->orderBy('todos.due_time')->orderBy('todos.id');

        return $limit ? $b->findAll($limit) : $b->findAll();
    }
}
