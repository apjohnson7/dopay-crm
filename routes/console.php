<?php

use App\Models\Invoice;
use App\Models\ReminderRule;
use App\Models\Communication;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;
use App\Models\Branch;
use App\Models\User;

/*
| Automatic payment reminders (7 days before, on due date, 3 and 7 days overdue).
| Messages are queued in `communications`; a channel worker (email / WhatsApp Business API) delivers them.
*/
Artisan::command('dopay:reminders', function () {
    $count = 0;
    foreach (ReminderRule::where('is_active', true)->get() as $rule) {
        $due = today()->subDays($rule->offset_days)->toDateString();
        Invoice::whereIn('status', ['approved', 'sent'])->whereDate('due_date', $due)->with('customer')->get()
            ->filter(fn ($i) => $i->balance() > 0)
            ->each(function ($i) use ($rule, &$count) {
                Communication::create(['customer_id' => $i->customer_id, 'channel' => $rule->channel, 'kind' => $rule->label, 'reference' => $i->number,
                    'recipient' => $rule->channel === 'Email' ? $i->customer->email : $i->customer->phone, 'status' => 'queued']);
                $count++;
            });
    }
    $this->info("Queued {$count} reminders.");
})->purpose('Queue payment reminders for due and overdue invoices');

/*
| First administrator for a clean install (no demo data).
*/
Artisan::command('dopay:admin {email} {name}', function (string $email, string $name) {
    $branch = Branch::orderBy('id')->first();
    $password = $this->secret('Password (min 12 characters)');
    $pin = $this->secret('Signing PIN (4 to 6 digits)');
    if (strlen((string) $password) < 12 || ! preg_match('/^\d{4,6}$/', (string) $pin)) {
        $this->error('Password must be at least 12 characters and the PIN 4 to 6 digits.');

        return 1;
    }
    $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'branch_id' => $branch?->id, 'job_title' => 'Super Administrator', 'password' => bcrypt($password), 'is_active' => true]);
    $u->setSigningPin($pin);
    $u->syncRoles(['Super Administrator']);
    $this->info("{$email} is a Super Administrator. Sign in and set up two-factor authentication under Security.");
})->purpose('Create or reset the first Super Administrator');

Schedule::command('dopay:reminders')->dailyAt('06:00');
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
