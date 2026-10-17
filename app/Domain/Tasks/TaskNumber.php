<?php

namespace App\Domain\Tasks;

use App\Models\Task;

final class TaskNumber
{
    public function next(): string
    {
        // True NUMERIC max of the trailing digits, not a lexicographic VARCHAR max.
        $max = (int) Task::withTrashed()
            ->selectRaw('MAX(CAST(SUBSTRING(task_number, 5) AS UNSIGNED)) AS max_num')
            ->value('max_num');

        return sprintf('TSK-%06d', $max + 1);
    }
}