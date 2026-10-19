<?php

namespace App\Livewire\AuditLogs;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.audit-logs.index', ['logs' => AuditLog::latest('id')->paginate(20)])->title('Audit Logs');
    }
}
