<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\User;
use App\Services\SalesLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesLeadJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_is_synced_as_a_sales_task(): void
    {
        $user = User::create([
            'name' => 'Sales User',
            'email' => 'sales-journey@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $lead = SalesLead::create([
            'code' => 'LEAD-TEST-1',
            'company_name' => 'شركة رحلة المبيعات',
            'owner_id' => $user->id,
            'stage' => 'contacted',
            'rating' => 'promising',
            'qualification' => 'evaluating',
            'priority' => 'medium',
            'responded' => true,
            'next_follow_up_at' => now()->addDay()->setTime(10, 0),
            'next_follow_up_type' => 'meeting',
            'next_follow_up_priority' => 'high',
            'next_follow_up_title' => 'اجتماع لفهم الاحتياج',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        app(SalesLeadService::class)->syncFollowUp($lead);

        $task = $lead->tasks()->firstOrFail();
        $this->assertTrue($task->is_follow_up);
        $this->assertSame('meeting', $task->type);
        $this->assertSame('اجتماع لفهم الاحتياج', $task->title);
        $this->assertSame('open', $task->status);
        $this->assertSame($user->id, $task->assigned_to);
    }

    public function test_qualified_lead_converts_to_customer_and_preserves_pre_contract_link(): void
    {
        $user = User::create([
            'name' => 'Sales User 2',
            'email' => 'sales-convert@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $lead = SalesLead::create([
            'code' => 'LEAD-TEST-2',
            'company_name' => 'شركة التحويل',
            'contact_name' => 'أحمد',
            'phone' => '0500000000',
            'email' => 'lead@example.test',
            'city' => 'الرياض',
            'sector' => 'التوزيع',
            'source' => 'referral',
            'owner_id' => $user->id,
            'stage' => 'negotiation',
            'rating' => 'hot',
            'qualification' => 'qualified',
            'priority' => 'high',
            'responded' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $customer = app(SalesLeadService::class)->convertToCustomer($lead, $user->id);
        $lead->refresh();

        $this->assertSame('شركة التحويل', $customer->name);
        $this->assertSame('sales_lead', $customer->source_type);
        $this->assertSame($lead->id, (int) $customer->source_id);
        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('won', $lead->stage);
        $this->assertNotNull($lead->converted_at);
        $this->assertDatabaseHas('sales_lead_activities', [
            'sales_lead_id' => $lead->id,
            'type' => 'تحويل إلى عميل',
        ]);
    }

    public function test_unqualified_lead_cannot_be_converted(): void
    {
        $user = User::create([
            'name' => 'Sales User 3',
            'email' => 'sales-blocked@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $lead = SalesLead::create([
            'code' => 'LEAD-TEST-3',
            'company_name' => 'شركة غير مؤهلة',
            'stage' => 'evaluation',
            'rating' => 'medium',
            'qualification' => 'evaluating',
            'priority' => 'medium',
            'responded' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        app(SalesLeadService::class)->convertToCustomer($lead, $user->id);
    }
}
