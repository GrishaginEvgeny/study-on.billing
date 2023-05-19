<?php

namespace App\Controller;

use App\DTO\TransactionDTO;
use App\Entity\Transaction;
use App\ErrorTemplate\ErrorTemplate;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/api/v1/transactions")
 */
class TransactionApiController extends AbstractController
{
    private TransactionRepository $transactionRepository;

    private SerializerInterface $serializer;

    private TranslatorInterface $translator;

    public function __construct(
        TransactionRepository $transactionRepository,
        SerializerInterface $serializer,
        TranslatorInterface $translator
    ) {
        $this->serializer = $serializer;
        $this->transactionRepository = $transactionRepository;
        $this->translator = $translator;
    }

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
     * @OA\Response(
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
     * @OA\Tag(name="TransactionsApi")
     * @Security(name="Bearer")
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => $this->translator->trans('errors.user.doesnt_auth', [], 'validators'),
            ], Response::HTTP_FORBIDDEN);
        }

        $jsonToDeserialize =
            json_encode([
                "type" => $request->query->get("type", null),
                "course_code" => $request->query->get("course_code", null),
                "skip_expired" => $request->query->get("skip_expired", null)
            ], JSON_THROW_ON_ERROR);
        $transactionDTO = $this->serializer
            ->deserialize($jsonToDeserialize, TransactionDTO::class, 'json');

        $typeDigit = null;
        switch ($transactionDTO->type) {
            case "payment":
                $typeDigit = Transaction::PAYMENT_TYPE;
                break;
            case "deposit":
                $typeDigit = Transaction::DEPOSIT_TYPE;
                break;
        }

        $transactions = $this->transactionRepository
            ->getTransactionsByFilters($typeDigit, $transactionDTO->course_code, $transactionDTO->skip_expired, $user);
        $response = [];
        foreach ($transactions as $transaction) {
            $response[] = [
                "id" => $transaction->getId(),
                "created_at" => $transaction->getCreatedAt()->format(DATE_ATOM),
                "type" => $transaction->getTypeCode(),
                "course_code" => $transaction->getCourse() ? $transaction->getCourse()->getCharacterCode() : null,
                "amount" => $transaction->getAmount(),
                "expired_at" => $transaction->getExpiredAt() ? $transaction->getExpiredAt()->format(DATE_ATOM) : null
            ];
        }
        return new JsonResponse($response);
    }
}
