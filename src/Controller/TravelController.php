<?php

namespace App\Controller;

use App\Service\TravelPriceCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TravelController extends AbstractController
{
    public function __construct(
        private TravelPriceCalculator $priceCalculator,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    #[Route('/api/travel/calculate', name: 'api_travel_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $data = $this->serializer->deserialize($request->getContent(), 'array', 'json');

        $violations = $this->validator->validate($data, $this->priceCalculator->getValidationConstraints());

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $price = $this->priceCalculator->calculate($data);
            return $this->json($price);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка при расчете цены'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}