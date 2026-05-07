<?php

declare(strict_types=1);

namespace App\Service\Paid;

use App\Model\Feedback;

class FeedbackService
{
    public function list(array $params)
    {
        return Feedback::when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($contact = data_get($params, 'contact'), function ($query) use ($contact) {
                $query->where('contact', $contact);
            })
            ->orderBy('created_time', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }
}