<?php

use App\Enums\InvoiceTemplate;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Quotations\CreateQuotationDraft;
use App\Livewire\Actions\Quotations\SendQuotation;
use App\Mail\QuotationMail;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quotations\QuotationDocumentData;
use App\Services\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function quotDocUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'QuotDoc User '.Str::random(4)]);

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function quotDocSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Steel Bar '.Str::random(4),
        'base_unit_id' => $unit->id,
        'selling_price' => '10000.00',
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);

    $location = Location::forceCreate([
        'name' => 'Doc Warehouse '.Str::random(6),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Kimara Steel Ltd',
        'type' => 'business',
        'phone' => '+255 712 000 999',
        'email' => 'orders@kimara.example',
        'address' => '5 Industrial Rd, Dar es Salaam',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    return compact('unit', 'product', 'location', 'customer');
}

/** Create and send a quotation, returning it loaded with relations for PDF building. */
function quotDocMakeSent(array $setup): Quotation
{
    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '14');
    app(Settings::class)->put('numbering.quotation_prefix', 'QUO');
    app(Settings::class)->put('numbering.digits', '5');
    app(Settings::class)->put('numbering.reset_yearly', false);
    app(Settings::class)->put('business.name', 'Test Company Ltd');
    app(Settings::class)->put('business.currency', 'TZS');

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '3',
            'unit_price' => '10000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    return $sent->load(['items.unit', 'location', 'customer']);
}

function bootstrapQuotPermissions(): void
{
    app(EnsureSystemRoles::class)();
}

// ─── PDF rendering ────────────────────────────────────────────────────────────

test('classic template renders a valid PDF containing "not a tax invoice" line', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);
    $data = app(QuotationDocumentData::class)->build($quotation);

    $pdf = Pdf::loadView('quotations.templates.classic', $data)->setPaper('a4', 'portrait');
    $bytes = (string) $pdf->output();

    expect($bytes)->toStartWith('%PDF');

    // Check the HTML contains the not-a-tax-invoice line.
    $html = view('quotations.templates.classic', $data)->render();
    expect($html)->toContain('This is not a tax invoice.');
});

test('every quotation template renders a valid PDF with the not-a-tax-invoice notice', function (string $templateKey, string $paper) {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);
    $data = app(QuotationDocumentData::class)->build($quotation);

    $template = InvoiceTemplate::from($templateKey);
    $pdf = Pdf::loadView('quotations.templates.'.$templateKey, $data)->setPaper(strtolower($paper), 'portrait');
    $bytes = (string) $pdf->output();

    expect($bytes)->toStartWith('%PDF');

    $html = view('quotations.templates.'.$templateKey, $data)->render();
    expect($html)->toContain('This is not a tax invoice.');
})->with([
    ['classic', 'A4'],
    ['modern', 'A4'],
    ['minimal', 'A4'],
    ['compact', 'A5'],
]);

test('draft quotation renders with DRAFT watermark in view', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.print']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('business.name', 'Test Co');
    app(Settings::class)->put('business.currency', 'TZS');

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '10000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $draft->load(['items.unit', 'location', 'customer']);
    $data = app(QuotationDocumentData::class)->build($draft);

    expect($data['watermark'])->toBe('DRAFT');

    $html = view('quotations.templates.classic', $data)->render();
    expect($html)->toContain('DRAFT');
});

test('expired quotation renders with EXPIRED watermark in view', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('business.name', 'Test Co');
    app(Settings::class)->put('business.currency', 'TZS');
    app(Settings::class)->put('numbering.quotation_prefix', 'QUO');
    app(Settings::class)->put('numbering.digits', '5');
    app(Settings::class)->put('numbering.reset_yearly', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->subDays(10)->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '10000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);
    $sent->load(['items.unit', 'location', 'customer']);

    expect($sent->isExpired())->toBeTrue();

    $data = app(QuotationDocumentData::class)->build($sent);
    expect($data['watermark'])->toBe('EXPIRED');
});

test('QuotationPdfController returns PDF inline for a sent quotation', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);

    $response = $this->get(route('quotations.pdf', ['quotation' => $quotation->id]).'?template=classic&paper=A4&mode=inline');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('QuotationPdfController rejects the thermal template', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);

    $response = $this->get(route('quotations.pdf', ['quotation' => $quotation->id]).'?template=thermal');
    $response->assertStatus(422);
});

test('QuotationPdfController returns 403 without quotations.print permission', function () {
    bootstrapQuotPermissions();
    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send']); // no print
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);

    $response = $this->get(route('quotations.pdf', ['quotation' => $quotation->id]));
    $response->assertForbidden();
});

// ─── Email ────────────────────────────────────────────────────────────────────

test('email sends one message with a PDF attachment', function () {
    bootstrapQuotPermissions();
    Mail::fake();

    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);
    $quotation->load(['items.unit', 'location', 'customer']);

    // Build the PDF bytes as the Livewire component would.
    $data = app(QuotationDocumentData::class)->build($quotation);
    $pdfBytes = (string) Pdf::loadView('quotations.templates.classic', $data)
        ->setPaper('a4', 'portrait')
        ->output();
    $filename = ($quotation->number ?? 'quotation').'-classic.pdf';

    Mail::to('recipient@example.com')->send(new QuotationMail(
        quotation: $quotation,
        recipientEmail: 'recipient@example.com',
        message: 'Please find the attached quotation.',
        pdfBytes: $pdfBytes,
        pdfFilename: $filename,
    ));

    Mail::assertSent(QuotationMail::class, function (QuotationMail $mail) use ($quotation, $filename): bool {
        return $mail->quotation->id === $quotation->id
            && $mail->recipientEmail === 'recipient@example.com'
            && collect($mail->attachments())->contains(
                fn ($attachment) => str_contains((string) json_encode($attachment), $filename)
            );
    });

    Mail::assertSentCount(1);
});

test('QuotationMail envelope uses the quotation number in the subject', function () {
    bootstrapQuotPermissions();

    $setup = quotDocSetup();
    $user = quotDocUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    $quotation = quotDocMakeSent($setup);

    $mailable = new QuotationMail(
        quotation: $quotation,
        recipientEmail: 'x@example.com',
        message: 'Hello',
        pdfBytes: 'fake-pdf-bytes',
        pdfFilename: 'test.pdf',
    );

    expect($mailable->envelope()->subject)->toContain($quotation->number);
});
