<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TravelControllerTest extends WebTestCase
{
    public function validDataProvider(): \Generator
    {
        yield 'Взрослый со скидкой при раннем бронировании' => [
            [
                'price' => 10000,
                'startDate' => '2027-05-01',
                'paymentDate' => '2026-11-15',
                'age' => 30,
            ],
            [
                'price' => 10000,
                'childDiscount' => 0,
                'earlyBookingDiscount' => 700,
                'finalPrice' => 9300,
            ],
        ];

        yield 'Ребенок до 3 лет' => [
            [
                'price' => 5000,
                'startDate' => '2027-05-01',
                'paymentDate' => '2026-11-15',
                'age' => 2,
            ],
            [
                'price' => 5000,
                'childDiscount' => 0,
                'earlyBookingDiscount' => 350,
                'finalPrice' => 4650,
            ],
        ];
    }

    public function testCalculateWithValidData(array $input, array $expectedOutput): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/travel/calculate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($input));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($expectedOutput, $responseData);
    }

    public function invalidDataProvider(): \Generator
    {
        yield 'Неверная цена' => [
            [
                'price' => 'invalid',
                'startDate' => '2027-05-01',
                'paymentDate' => '2026-11-15',
                'age' => 30,
            ],
            ['errors' => ['price' => 'Это значение должно быть числом']],
        ];

        yield 'Отрицательный возраст' => [
            [
                'price' => 10000,
                'startDate' => '2027-05-01',
                'paymentDate' => '2026-11-15',
                'age' => -5,
            ],
            ['errors' => ['age' => 'Это значение должно быть больше или равно 0']],
        ];
    }

    public function testCalculateWithInvalidData(array $input, array $expectedErrors): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/travel/calculate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($input));

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $responseData);
        $this->assertEquals($expectedErrors, $responseData['errors']);
    }

    public function testCalculateWithInvalidPaymentDate(): void
    {
        $client = static::createClient();

        $input = [
            'price' => 10000,
            'startDate' => '2027-05-01',
            'paymentDate' => '2027-06-01',
            'age' => 30,
        ];

        $client->request('POST', '/api/travel/calculate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($input));

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Дата платежа не может быть позднее даты начала путешествия', $responseData['error']);
    }
}