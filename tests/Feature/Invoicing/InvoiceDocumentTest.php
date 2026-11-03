<?php

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Invoicing\CreateInvoiceDraft;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Invoicing\RenderInvoiceDocument;
use App\Livewire\Actions\Invoicing\VoidInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Invoicing\InvoiceDocumentData;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function docUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'Doc Test User']);

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * Build a minimal issued invoice with one line item (no batch tracking).
 * The caller must already be actingAs a user with invoices.create + invoices.issue.
 */
function makeIssuedInvoice(string $idempotencyKey = 'IDEMP-DOC-TEST'): Invoice
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Test Product '.Str::random(4),
        'base_unit_id' => $unit->id,
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);

    $location = Location::forceCreate([
        'name' => 'Test Warehouse '.Str::random(6),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Bright Traders Ltd',
        'type' => 'business',
        'phone' => '+255 711 999 888',
        'address' => '10 Uhuru St, Dodoma',
        'tin' => 'TIN-DOC-001',
        'vrn' => 'VRN-DOC-001',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    // Put stock into the location
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '100.000',
    ]);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-04',
        'due_date' => '2026-11-18',
        'notes' => 'Sample note',
        'terms' => 'Net 14',
        'items' => [
            [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '5',
                'unit_price' => '250000.00',
                'discount_amount' => '0.00',
            ],
        ],
    ]);

    $result = app(IssueInvoice::class)($draft, $idempotencyKey);

    return $result->invoice->fresh(['items.unit', 'location', 'customer']);
}

/** Ensure the system roles / permission graph is bootstrapped once per suite. */
function bootstrapDocPermissions(): void
{
    // EnsureSystemRoles is idempotent — safe to call in every test
    app(EnsureSystemRoles::class)();
}

// ─── PDF bytes ────────────────────────────────────────────────────────────────

test('issued invoice renders a valid PDF for the classic/A4 combination', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-PDF-CLASSIC');

    $result = app(RenderInvoiceDocument::class)(
        $invoice,
        InvoiceTemplate::Classic,
        'A4',
        'print',
    );

    expect($result->bytes)->toStartWith('%PDF');
});

test('every template and its supported paper sizes produce a valid PDF', function (InvoiceTemplate $template, string $paper) {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-TMPL-'.$template->value.'-'.Str::random(6));

    $result = app(RenderInvoiceDocument::class)($invoice, $template, $paper, 'print');

    expect($result->bytes)->toStartWith('%PDF');
})->with(
    collect(InvoiceTemplate::cases())
        ->flatMap(fn (InvoiceTemplate $t) => collect($t->supportedPapers())
            ->map(fn (string $p) => [$t, $p]))
        ->all()
);

// ─── Customer snapshot ────────────────────────────────────────────────────────

test('view-model uses customer snapshot after customer is updated post-issue', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-SNAP-01');

    // Mutate the live customer record — printed invoice must show the snapshot
    $invoice->customer->update(['name' => 'Changed Name After Issue', 'phone' => '+255 000 000 000']);

    $data = app(InvoiceDocumentData::class)->build($invoice->fresh(), false);

    expect($data['customer']['name'])->toBe('Bright Traders Ltd')
        ->and($data['customer']['phone'])->toBe('+255 711 999 888');
});

// ─── Original / duplicate ─────────────────────────────────────────────────────

test('first render of an issued invoice creates an original document', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-ORIG-01');

    $result = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Classic, 'A4', 'print');

    expect($result->isDuplicate)->toBeFalse();

    $doc = InvoiceDocument::query()->find($result->documentId);
    expect($doc)->not->toBeNull()
        ->and($doc->is_duplicate)->toBeFalse()
        ->and($doc->template_key)->toBe('classic')
        ->and($doc->paper)->toBe('A4')
        ->and($doc->action)->toBe('print');
});

test('second render of an issued invoice creates a duplicate document', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-DUP-01');

    $first = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Classic, 'A4', 'print');
    $second = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Modern, 'A4', 'print');

    expect($first->isDuplicate)->toBeFalse()
        ->and($second->isDuplicate)->toBeTrue();

    $secondDoc = InvoiceDocument::query()->find($second->documentId);
    expect($secondDoc->is_duplicate)->toBeTrue();
});

test('sequential renders produce exactly one original and all subsequent are duplicates', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-DUP-SEQ');
    $render = app(RenderInvoiceDocument::class);

    $r1 = $render($invoice, InvoiceTemplate::Classic, 'A4', 'print');
    $r2 = $render($invoice, InvoiceTemplate::Minimal, 'A4', 'print');
    $r3 = $render($invoice, InvoiceTemplate::Modern, 'A4', 'print');

    expect($r1->isDuplicate)->toBeFalse()
        ->and($r2->isDuplicate)->toBeTrue()
        ->and($r3->isDuplicate)->toBeTrue();

    $originals = InvoiceDocument::query()
        ->where('invoice_id', $invoice->id)
        ->where('is_duplicate', false)
        ->count();

    expect($originals)->toBe(1);
});

// ─── Draft watermark ──────────────────────────────────────────────────────────

test('draft invoice renders with DRAFT watermark and is not persisted in invoice_documents', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.print']);
    $this->actingAs($user);

    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)), 'name' => 'Draft Item', 'base_unit_id' => $unit->id,
        'tax_type' => 'standard', 'reorder_level' => 0, 'track_batches' => false, 'is_active' => true,
    ]);
    $location = Location::forceCreate(['name' => 'WH '.Str::random(6), 'type' => 'warehouse', 'is_active' => true]);
    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)), 'name' => 'Draft Customer',
        'type' => 'individual', 'is_active' => true, 'is_walk_in' => false,
    ]);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $data = app(InvoiceDocumentData::class)->build($draft, false);
    expect($data['watermark'])->toBe('DRAFT');

    // Render should succeed but no document record is created
    $result = app(RenderInvoiceDocument::class)($draft, InvoiceTemplate::Classic, 'A4', 'print');

    expect($result->bytes)->toStartWith('%PDF')
        ->and($result->documentId)->toBeNull();

    expect(InvoiceDocument::query()->where('invoice_id', $draft->id)->count())->toBe(0);
});

// ─── Void watermark ───────────────────────────────────────────────────────────

test('void invoice renders with VOID watermark and is not persisted in invoice_documents', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.void', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-VOID-01');

    app(VoidInvoice::class)($invoice, 'Test void reason');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Void);

    $data = app(InvoiceDocumentData::class)->build($invoice, false);
    expect($data['watermark'])->toBe('VOID');

    $result = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Classic, 'A4', 'print');

    expect($result->bytes)->toStartWith('%PDF')
        ->and($result->documentId)->toBeNull();

    expect(InvoiceDocument::query()->where('invoice_id', $invoice->id)->count())->toBe(0);
});

// ─── Save PDF ─────────────────────────────────────────────────────────────────

test('save action stores the PDF on the private local disk with correct path, size and sha256', function () {
    Storage::fake('local');

    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-SAVE-01');

    $result = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Classic, 'A4', 'save');

    expect($result->bytes)->toStartWith('%PDF');

    $doc = InvoiceDocument::query()->find($result->documentId);
    expect($doc)->not->toBeNull()
        ->and($doc->action)->toBe('save')
        ->and($doc->path)->not->toBeNull();

    // File must exist on the fake disk
    expect(Storage::disk('local')->exists($doc->path))->toBeTrue();

    // Size and sha256 must match the bytes
    expect($doc->size_bytes)->toBe(strlen($result->bytes))
        ->and($doc->sha256)->toBe(hash('sha256', $result->bytes));

    // Path format: invoices/{year}/{slug}.pdf
    expect($doc->path)->toStartWith('invoices/'.now()->format('Y').'/')
        ->and($doc->path)->toEndWith('.pdf');
});

// ─── Download route auth ──────────────────────────────────────────────────────

test('download route is rejected for user without invoices.print', function () {
    bootstrapDocPermissions();

    // Create the invoice and document as a privileged user
    $admin = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($admin);

    Storage::fake('local');
    $invoice = makeIssuedInvoice('IDEMP-DL-AUTH-01');
    $result = app(RenderInvoiceDocument::class)($invoice, InvoiceTemplate::Classic, 'A4', 'save');
    $doc = InvoiceDocument::query()->findOrFail($result->documentId);

    // Now switch to a user without invoices.print
    $noPerms = docUser(['invoices.view']);
    $this->actingAs($noPerms);

    $response = $this->get(route('invoices.document.download', [
        'invoice' => $invoice->id,
        'document' => $doc->id,
    ]));

    $response->assertForbidden();
});

test('PDF render route is rejected for user without invoices.print', function () {
    bootstrapDocPermissions();

    $admin = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($admin);

    $invoice = makeIssuedInvoice('IDEMP-PDF-AUTH-01');

    $noPerms = docUser(['invoices.view']);
    $this->actingAs($noPerms);

    $response = $this->get(route('invoices.pdf', [
        'invoice' => $invoice->id,
        'template' => 'classic',
        'paper' => 'A4',
        'mode' => 'inline',
    ]));

    $response->assertForbidden();
});

// ─── Invalid template / paper ─────────────────────────────────────────────────

test('PDF route rejects an unknown template value with 422', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-INV-TMPL-01');

    $response = $this->get(route('invoices.pdf', [
        'invoice' => $invoice->id,
        'template' => 'nonexistent_template',
        'paper' => 'A4',
        'mode' => 'inline',
    ]));

    $response->assertStatus(422);
});

test('PDF route rejects a paper size not supported by the selected template', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-INV-PAPER-01');

    // classic only supports A4 — 80mm is thermal-only
    $response = $this->get(route('invoices.pdf', [
        'invoice' => $invoice->id,
        'template' => 'classic',
        'paper' => '80mm',
        'mode' => 'inline',
    ]));

    $response->assertStatus(422);
});

// ─── Logo from local path ─────────────────────────────────────────────────────

test('logo is embedded as base64 data-url when the file exists at the configured path', function () {
    bootstrapDocPermissions();
    $this->actingAs(docUser(['invoices.create']));

    // Write a tiny 1×1 white PNG to a temp file
    $pngBytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
    $tmpPath = tempnam(sys_get_temp_dir(), 'inv_logo_').'.png';
    file_put_contents($tmpPath, $pngBytes);

    app(Settings::class)->put('business.logo_path', $tmpPath);

    $unit = Unit::forceCreate(['name' => 'Pc '.Str::random(4), 'abbreviation' => 'pc']);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)), 'name' => 'Logo Item', 'base_unit_id' => $unit->id,
        'tax_type' => 'standard', 'reorder_level' => 0, 'track_batches' => false, 'is_active' => true,
    ]);
    $location = Location::forceCreate(['name' => 'WH '.Str::random(6), 'type' => 'warehouse', 'is_active' => true]);
    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)), 'name' => 'Logo Co',
        'type' => 'individual', 'is_active' => true, 'is_walk_in' => false,
    ]);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]);

    $data = app(InvoiceDocumentData::class)->build($draft, false);

    expect($data['company']['logo'])->toStartWith('data:image/png;base64,');

    @unlink($tmpPath);
});

test('logo is null when the path is not configured', function () {
    bootstrapDocPermissions();
    $this->actingAs(docUser(['invoices.create']));

    app(Settings::class)->put('business.logo_path', '');

    $unit = Unit::forceCreate(['name' => 'Pc '.Str::random(4), 'abbreviation' => 'pc']);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)), 'name' => 'No Logo Item', 'base_unit_id' => $unit->id,
        'tax_type' => 'standard', 'reorder_level' => 0, 'track_batches' => false, 'is_active' => true,
    ]);
    $location = Location::forceCreate(['name' => 'WH '.Str::random(6), 'type' => 'warehouse', 'is_active' => true]);
    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)), 'name' => 'No Logo Co',
        'type' => 'individual', 'is_active' => true, 'is_walk_in' => false,
    ]);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]);

    $data = app(InvoiceDocumentData::class)->build($draft, false);

    expect($data['company']['logo'])->toBeNull();
});

test('dompdf remote access is disabled in config', function () {
    expect(config('dompdf.options.enable_remote'))->toBeFalse();
});

// ─── Amount in words ──────────────────────────────────────────────────────────

test('amount in words is correct for whole TZS amounts', function () {
    $svc = app(InvoiceDocumentData::class);

    // 1,250,000.00 → "One million two hundred and fifty thousand Shillings Only"
    expect($svc->amountInWords('1250000.00', 'TZS'))
        ->toBe('One million two hundred and fifty thousand Shillings Only');
});

test('amount in words is correct for TZS with cents', function () {
    $svc = app(InvoiceDocumentData::class);

    // 18,500.50 → "Eighteen thousand five hundred Shillings and Fifty Cents"
    expect($svc->amountInWords('18500.50', 'TZS'))
        ->toBe('Eighteen thousand five hundred Shillings and Fifty Cents');
});

test('amount in words handles zero correctly', function () {
    $svc = app(InvoiceDocumentData::class);

    expect($svc->amountInWords('0.00', 'TZS'))
        ->toBe('Zero Shillings Only');
});

// ─── Fiscal block ─────────────────────────────────────────────────────────────

test('fiscal data is null when invoice has no fiscal columns set', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-FISCAL-NULL');
    $data = app(InvoiceDocumentData::class)->build($invoice, false);

    expect($data['fiscal'])->toBeNull();
});

test('fiscal data is returned when fiscal columns are populated on the invoice', function () {
    bootstrapDocPermissions();
    $user = docUser(['invoices.create', 'invoices.issue', 'invoices.print']);
    $this->actingAs($user);

    $invoice = makeIssuedInvoice('IDEMP-FISCAL-DATA');

    // Simulate what a FiscalProvider integration would write
    $invoice->update([
        'fiscal_number' => 'TRA-2026-000001',
        'fiscal_verification_code' => 'ABCD-1234-EFGH',
        'fiscal_qr_payload' => 'https://verify.tra.go.tz/?code=ABCD-1234-EFGH',
        'fiscal_status' => 'submitted',
    ]);

    $invoice->refresh();
    $data = app(InvoiceDocumentData::class)->build($invoice, false);

    expect($data['fiscal'])->not->toBeNull()
        ->and($data['fiscal'])->toHaveKey('number')
        ->and($data['fiscal']['number'])->toBe('TRA-2026-000001');
});
