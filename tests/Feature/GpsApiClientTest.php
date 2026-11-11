<?php

namespace Tests\Feature;

use App\Models\GpsProvider;
use App\Services\GpsApiClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GpsApiClientTest extends TestCase
{
    public function test_ats_posts_credentials_and_filters_fleet(): void
    {
        $provider = new GpsProvider([
            'base_url' => 'https://gps.example/api', 'client_code' => 'test',
            'api_key_encrypted' => Crypt::encryptString('secret'),
            'config_json' => ['protocol' => 'ats', 'timezone' => 'America/Bogota'],
        ]);
        $row = ['Placa' => 'EQO656', 'Fecha_reporte' => '2025-01-01 12:00:00',
            'Latitud' => '4.625', 'Longitud' => '-74.107', 'Velocidad' => '0'];
        Http::fake(['*' => Http::response(['Codigo' => '1', 'Posiciones' => [$row, array_replace($row, ['Placa' => 'OTHER'])]])]);
        $client = new GpsApiClient;
        $response = $client->request($provider, 'EQO656,ABC123');
        $positions = $client->positions($provider, $response, 'EQO656,ABC123');
        $this->assertCount(1, $positions);
        $this->assertSame(0.0, $positions[0]['speed']);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['Placa'] === '*' && $request['Password'] === 'secret');
        $this->assertArrayNotHasKey('api_key_encrypted', $provider->toArray());
    }

    public function test_http_200_with_business_error_is_rejected(): void
    {
        $provider = new GpsProvider(['base_url' => 'https://gps.example/api', 'client_code' => 'test',
            'api_key_encrypted' => Crypt::encryptString('secret'), 'config_json' => ['protocol' => 'ats']]);
        Http::fake(['*' => Http::response(['Codigo' => '0', 'Respuesta' => 'Usuario inactivo'])]);
        $client = new GpsApiClient;
        $this->expectException(\RuntimeException::class);
        $client->positions($provider, $client->request($provider, 'EQO656'), 'EQO656');
    }
}
