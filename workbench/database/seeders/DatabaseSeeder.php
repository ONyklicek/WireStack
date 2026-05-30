<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Task;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->create([
            'name' => 'Amelia Stone',
            'email' => 'amelia@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'bio' => 'Owns product configuration, release notes, and customer rollouts.',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Mason Carter',
            'email' => 'mason@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'bio' => 'Coordinates approvals and internal QA for operations changes.',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Sofia Bennett',
            'email' => 'sofia@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
            'bio' => 'Maintains customer-facing copy, presets, and workflows.',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Ethan Brooks',
            'email' => 'ethan@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'bio' => 'Observes usage metrics and handles stakeholder reporting.',
            'is_active' => false,
        ]);

        $tasks = [
            ['title' => 'Draft onboarding checklist', 'status' => 'todo', 'priority' => 'high', 'owner_name' => 'Amelia Stone', 'sort_order' => 1, 'completed' => false, 'due_at' => now()->addDay()],
            ['title' => 'Review bulk action copy', 'status' => 'in_progress', 'priority' => 'medium', 'owner_name' => 'Sofia Bennett', 'sort_order' => 2, 'completed' => false, 'due_at' => now()->addDays(2)],
            ['title' => 'Verify audit event payloads', 'status' => 'blocked', 'priority' => 'high', 'owner_name' => 'Mason Carter', 'sort_order' => 3, 'completed' => false, 'due_at' => now()->addDays(4)],
            ['title' => 'Ship sortable migration notes', 'status' => 'review', 'priority' => 'medium', 'owner_name' => 'Amelia Stone', 'sort_order' => 4, 'completed' => false, 'due_at' => now()->addDays(5)],
            ['title' => 'Finalize filter defaults', 'status' => 'done', 'priority' => 'low', 'owner_name' => 'Ethan Brooks', 'sort_order' => 5, 'completed' => true, 'due_at' => now()->subDay()],
            ['title' => 'Publish docs landing page', 'status' => 'todo', 'priority' => 'high', 'owner_name' => 'Sofia Bennett', 'sort_order' => 6, 'completed' => false, 'due_at' => now()->addWeek()],
        ];

        foreach ($tasks as $task) {
            Task::query()->create($task);
        }

        $invoices = [
            [
                'number' => 'INV-1001',
                'customer' => 'Northwind Traders',
                'status' => 'paid',
                'issued_at' => now()->subDays(12),
                'items' => [
                    ['product' => 'Mechanical keyboard', 'quantity' => 2, 'unit_price' => 1200],
                    ['product' => 'Wireless mouse', 'quantity' => 3, 'unit_price' => 450],
                    ['product' => '27" monitor', 'quantity' => 1, 'unit_price' => 5600],
                ],
            ],
            [
                'number' => 'INV-1002',
                'customer' => 'Globex Corporation',
                'status' => 'pending',
                'issued_at' => now()->subDays(5),
                'items' => [
                    ['product' => 'Standing desk', 'quantity' => 1, 'unit_price' => 8900],
                    ['product' => 'Ergonomic chair', 'quantity' => 4, 'unit_price' => 2300],
                ],
            ],
            [
                'number' => 'INV-1003',
                'customer' => 'Acme Industries',
                'status' => 'overdue',
                'issued_at' => now()->subDays(33),
                'items' => [
                    ['product' => 'Software license', 'quantity' => 5, 'unit_price' => 990],
                    ['product' => 'Priority support', 'quantity' => 1, 'unit_price' => 3500],
                ],
            ],
        ];

        foreach ($invoices as $invoice) {
            $items = $invoice['items'];
            unset($invoice['items']);

            $model = Invoice::query()->create($invoice);

            foreach ($items as $item) {
                $model->items()->create([
                    ...$item,
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        }
    }
}
