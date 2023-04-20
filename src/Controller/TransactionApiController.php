<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/api/v1/transactions")
 */
class TransactionApiController extends AbstractController
{
    /**
     * @Route("", name="app_transactions", methods={"GET"})
     * @Security(name="Bearer")
     */
    public function transactions(Request $request, TransactionRepository $transactionRepository, CourseRepository $courseRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Вы не авторизованы.',
            ], Response::HTTP_FORBIDDEN);
        }

        $type = $request->query->get("type", null);
        $courseCode = $request->query->get("course_code", null);
        $skipExpired = $request->query->get("skip_expired", null);

        $course = $courseRepository->findOneBy(['CharacterCode' => $courseCode]);

        if(!($type === 'deposit' || $type === 'payment') && $type){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Указанный тип не равен "deposit" или "payment".',
            ], Response::HTTP_BAD_REQUEST);
        } else {
            $type = $type == "deposit" ? 1 : 0;
        }

        if(($skipExpired !== '1' && $skipExpired !== '0') && $skipExpired !== null){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Флаг не равен "true" или "false".',
            ], Response::HTTP_BAD_REQUEST);
        } else {
            $skipExpired = $skipExpired === '1';
        }

        if(!$course && $courseCode){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Курс с таким символьным кодом не найден.',
            ], Response::HTTP_BAD_REQUEST);
        }


        $transactions = $transactionRepository->getTransactionsByFilters($type,$courseCode,$skipExpired, $user);
        $responseArray = [];
        foreach ($transactions as $transaction){
            $responseArray[] = [
                "id" => $transaction->getId(),
                "created_at" => $transaction->getDate()->format(DATE_ISO8601),
                "type" => $transaction->getType() == 1 ? "deposit" : "payment",
                "course_code" => $transaction->getCourse()->getCharacterCode(),
                "amount" => $transaction->getCourse()->getCost(),
                "expired_at" => $transaction->getValidTo() ? $transaction->getValidTo()->format(DATE_ISO8601) : null
            ];
        }
        return new JsonResponse($responseArray);
    }
}
