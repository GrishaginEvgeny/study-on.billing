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
use OpenApi\Annotations as OA;


/**
 * @Route("/api/v1/transactions")
 */
class TransactionApiController extends AbstractController
{
    /**
     * @Route("", name="app_transactions", methods={"GET"})
     * @OA\Get (
     *     path="/api/v1/transactions",
     *     summary="История транзакций определённого пользователя",
     *     description="Запрос на историю транзакций определённого пользователя с фильтрам: по символьному коду, по типу
     транзакции и флаг пропуска истёкших арендованных курсов."
     * )
     *
     * @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="type",
     *          type="string",
     *          example="payment|deposit|null",
     *        ),
     *        @OA\Property(
     *          property="skip_expired",
     *          type="bool",
     *          example="true|false|null",
     *        ),
     *        @OA\Property(
     *          property="course_code",
     *          type="string",
     *        ),
     *     )
     *)
     *  @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          type="array",
     *     @OA\Items(
     *        @OA\Property(
     *          property="id",
     *          type="integer",
     *        ),
     *        @OA\Property(
     *          property="created_at",
     *          type="date",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="string",
     *          example="payment|deposit"
     *        ),
     *        @OA\Property(
     *          property="course_code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="amount",
     *          type="float",
     *        ),
     *        @OA\Property(
     *          property="expired_at",
     *          type="date",
     *        ),
     *     )
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="404",
     *     description="Ответ при запросе к покупке курса с несуществующим символьным кодом",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="404"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с таким символьным кодом не найден."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="400/1",
     *     description="Ответ при запросе c фильтром на тип транзакции, но когда тип указан неверно.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="400"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Указанный тип не равен deposit или payment."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="400/2",
     *     description="Ответ при запросе c фильтром на флаг пропуска истёкших арендованных курсов, но передано не булевое значение.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="400"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Флаг не равен true или false."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="401",
     *     description="Ответ при запросе неавторизированным пользователем",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Вы не авторизованы."
     *        ),
     * )
     * )
     * @OA\Response(
     *     response="400-5xx",
     *     description="Ответ при неизвестной ошибке",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Tag(name="CourseApi")
     * @Security(name="Bearer")
     */
    public function transactions(Request $request, TransactionRepository $transactionRepository, CourseRepository $courseRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Вы не авторизованы.',
            ], Response::HTTP_FORBIDDEN);
        }

        $type = $request->query->get("type", null);
        $courseCode = $request->query->get("course_code", null);
        $skipExpired = $request->query->get("skip_expired", null);


        $course = $courseRepository->findOneBy(['characterCode' => $courseCode]);

        if(!($type === 'deposit' || $type === 'payment') && !is_null($type)){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Указанный тип не равен "deposit" или "payment".',
            ], Response::HTTP_BAD_REQUEST);
        }

        if(!is_null($type)) {
            $type = $type === "deposit" ? Transaction::DEPOSIT_TYPE : Transaction::PAYMENT_TYPE;
        }


        if(($skipExpired !== '1' && $skipExpired !== '0') && !is_null($skipExpired)){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Флаг не равен "true" или "false".',
            ], Response::HTTP_BAD_REQUEST);
        }

        $skipExpired = $skipExpired === '1';

        if(!$course && $courseCode){
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с таким символьным кодом не найден.',
            ], Response::HTTP_NOT_FOUND);
        }


        $transactions = $transactionRepository->getTransactionsByFilters($type, $courseCode, $skipExpired, $user);
        $response = [];
        foreach ($transactions as $transaction){
            $response[] = [
                "id" => $transaction->getId(),
                "created_at" => $transaction->getCreatedAt()->format(DATE_ATOM),
                "type" => $transaction->getTypeCode(),
                "course_code" => $transaction->getCourse() ? $transaction->getCourse()->getCharacterCode() : null,
                "amount" =>  $transaction->getAmount(),
                "expired_at" => $transaction->getExpiredAt() ? $transaction->getExpiredAt()->format(DATE_ATOM) : null
            ];
        }
        return new JsonResponse($response);
    }
}
