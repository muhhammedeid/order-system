<?php

namespace Tests\Unit\WhatsApp\Templates;

use App\Support\WhatsApp\Templates\WhatsAppTemplateVariables;
use PHPUnit\Framework\TestCase;

class WhatsAppTemplateVariablesTest extends TestCase
{
    public function test_it_extracts_allowlisted_tokens_with_optional_spacing(): void
    {
        $this->assertSame(
            ['customer_name', 'business_name'],
            WhatsAppTemplateVariables::tokens('Hello {{customer_name}} from {{ business_name }}'),
        );

        $this->assertSame([], WhatsAppTemplateVariables::unknownTokens('Hello {{customer_name}} from {{business_name}}'));
    }

    public function test_unknown_tokens_are_reported(): void
    {
        $this->assertSame(['1+1'], WhatsAppTemplateVariables::unknownTokens('{{ 1+1 }}'));
        $this->assertSame(['order_total'], WhatsAppTemplateVariables::unknownTokens('Total: {{order_total}}'));
        $this->assertSame(['CUSTOMER_NAME'], WhatsAppTemplateVariables::unknownTokens('{{CUSTOMER_NAME}}'));
        $this->assertSame(['customer name'], WhatsAppTemplateVariables::unknownTokens('{{ customer name }}'));
    }

    public function test_it_detects_product_context_usage(): void
    {
        $this->assertTrue(WhatsAppTemplateVariables::usesProductContext('{{ product_name }}'));
        $this->assertTrue(WhatsAppTemplateVariables::usesProductContext('{{product_code}}'));
        $this->assertFalse(WhatsAppTemplateVariables::usesProductContext('{{ customer_name }} {{ business_name }}'));
    }

    public function test_canonicalize_normalizes_spacing_only(): void
    {
        $this->assertSame('{{customer_name}}', WhatsAppTemplateVariables::canonicalize('{{ customer_name }}'));
        $this->assertSame('{{1+1}}', WhatsAppTemplateVariables::canonicalize('{{ 1+1 }}'));
        $this->assertSame('no tokens here', WhatsAppTemplateVariables::canonicalize('no tokens here'));
    }
}
