<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id'           => $this->id,
            'category'     => $data['category'] ?? 'system',
            'title'        => $data['title'] ?? 'Notification',
            'body'         => $data['body'] ?? '',
            'task_id'      => $data['task_id'] ?? null,
            'task_number'  => $data['task_number'] ?? null,
            'expense_id'   => $data['expense_id'] ?? null,
            'expense_no'   => $data['expense_no'] ?? null,
            'amount'       => $data['amount'] ?? null,
            'project_name' => $data['project_name'] ?? null,
            'due_date'     => $data['due_date'] ?? null,
            'read_at'      => $this->read_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),

            'request_id'   => $data['request_id'] ?? null,
            'meeting_id'   => $data['meeting_id'] ?? null,
            'starts_at'    => $data['starts_at'] ?? null,
            'step_number'  => $data['step_number'] ?? null,
            'total_steps'  => $data['total_steps'] ?? null,
            'document_id'  => $data['document_id'] ?? null,
            'version'      => $data['version'] ?? null,
        ];
    }
}
