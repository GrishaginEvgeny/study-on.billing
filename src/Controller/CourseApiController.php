<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Services\PaymentService;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

/**
 * @Route("/api/v1/courses")
 */
class CourseApiController extends AbstractController
{
    /**
     * @Route("", name="app_courses", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/courses",
     *     summary="Все курсы сервиса",
     *     description="Запрос на получение всех курсов сервиса. Дополнительных параметров не имеет."
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *      @OA\Property(
     *     type="array",
     *     @OA\Items(
     *        @OA\Property(
     *          property="character_code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *        ),
     *     )
     * )
     *  )
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
     */
    public function courses(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();
        $arrayedCourses = [];
        foreach ($courses as $course){
            $arrayedCourses[] = [
                'character_code' => $course->getCharacterCode(),
                'type' => $course->getStringedType(),
                'price' => $course->getCost()
            ];
        }
        return new JsonResponse($arrayedCourses);
    }

    /**
     * @Route("/{code}", name="app_course", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/courses/{code}",
     *     summary="Определённый курс",
     *     description="Запрос на получение определённого курса по его символьному коду."
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="character_code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="404",
     *     description="Ответ при запросе к курсу с несуществующим символьным кодом",
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
     */
    public function course(
        string $code,
        CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['CharacterCode' => $code]);
        if (!$course) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с таким символьным кодом не найден.',
            ], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse([
            "character_code"=> $course->getCharacterCode(),
            "type"=> $course->getStringedType(),
            "price"=> $course->getCost()
        ]);
    }

    /**
     * @Route("/{code}/pay", name="app_course_pay", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses/{code}/pay",
     *     summary="Оплата курса",
     *     description="Запрос на оплату конкретного курса по символьному коду."
     * )
     *  @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="success",
     *          type="bool",
     *          example="true"
     *        ),
     *        @OA\Property(
     *          property="course_type",
     *          type="string",
     *          example="free|rent|buy"
     *        ),
     *        @OA\Property(
     *          property="expire_at",
     *          type="date",
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
     *     response="406/1",
     *     description="Ответ при запросе к покупке курса c недостаточным балансом",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="У вас не достаточно средств на счёте для (аренды|покупки) этого курса."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/2",
     *     description="Ответ при аренде курса, аренда которого ещё не истекла.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Этот курс уже арендован и длительность аренды ещё не истекла."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/3",
     *     description="Ответ при покупке курса, когда он уже приобретён.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Этот курс уже куплен."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/3",
     *     description="Ответ при покупке курса, когда он уже приобретён.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Этот курс уже куплен."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/4",
     *     description="Ответ при запросе к покупке бесплатного курса",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Этот курс бесплатен и не требует покупки."
     *        ),
     *     )
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
    public function pay(
        string $code,
        CourseRepository $courseRepository,
        PaymentService $paymentService,
        TransactionRepository $transactionRepository): JsonResponse{
        $course = $courseRepository->findOneBy(['CharacterCode' => $code]);
        $user = $this->getUser();
        if (!$course) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с таким символьным кодом не найден.',
            ], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Вы не авторизованы.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if($course->getStringedType() === 'free') {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => 'Этот курс бесплатен и не требует покупки.',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        if($user->getBalance() < $course->getCost()) {
            $textForResponse = $course->getStringedType() === 'rent' ? 'аренды' : 'покупки';
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => "У вас не достаточно средств на счёте для ".
                "{$textForResponse} этого курса.",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $existedTransaction = $course->getStringedType() === "buy" ? $transactionRepository
            ->findOneBy(['transactionUser' => $user, 'Course' => $course, 'type' => 0]) :
            $transactionRepository->getTransactionOnTypeRentWithMaxDate($course, $user);
        if($existedTransaction){
            if($existedTransaction->getCourse()->getStringedType() === 'rent'
                && $existedTransaction->getValidTo() > new \DateTimeImmutable('now')
            ){
                return new JsonResponse([
                    'code' => Response::HTTP_NOT_ACCEPTABLE,
                    'message' => 'Этот курс уже арендован и длительность аренды ещё не истекла.',
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
            if($existedTransaction->getCourse()->getStringedType() === 'buy') {
                return new JsonResponse([
                    'code' => Response::HTTP_NOT_ACCEPTABLE,
                    'message' => 'Этот курс уже куплен.',
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
        }

        try {
            $transaction = $paymentService->makePayment($user, $course);
            $responseArray = [
                "success" => true,
                "course_type"=> $transaction->getCourse()->getStringedType(),
            ];
            if($transaction->getValidTo()){
                $responseArray["expires_at"] = $transaction->getValidTo()->format(DATE_ATOM);
            }
            return new JsonResponse($responseArray,Response::HTTP_CREATED);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $exception->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
