<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Services\PaymentService;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

/**
 * @Route("/api/v1/courses")
 */
class CourseApiController extends AbstractController
{
    /**
     * @param $requestContent
     * @param CourseRepository $courseRepository
     * @return bool|JsonResponse
     */
    private function validate($requestContent, CourseRepository $courseRepository, bool $isEdit = false, string $previousCode = null)
    {
        $fields = ["type" => "Тип", "title" => "Название", "code" => "Символьный код", "price" => "Стоимость курса"];
        foreach ($fields as $key => $field) {
            if (!array_key_exists($key, $requestContent)) {
                return new JsonResponse([
                    "code" => Response::HTTP_NOT_ACCEPTABLE,
                    "message" => "В запросе отсутствует поле \"{$field}\"."
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
        }
        if (strlen($requestContent["code"]) > 255 || strlen($requestContent["code"]) < 1) {
            $textResponse = strlen($requestContent["code"]) > 255 ? 'длинной более 255 символов' : 'пустым';
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => "Поле \"Cимвольный код\" не должно быть {$textResponse}."
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $requestContent["code"])) {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => 'В поле "Cимвольный код" могут содержаться только цифры и латиница.'
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if (strlen($requestContent["title"]) > 255 || strlen($requestContent["title"]) < 1) {
            $textResponse = strlen($requestContent["title"]) > 255 ? 'длинной более 255 символов' : 'пустым';
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => "Поле \"Название код\" не должно быть {$textResponse}."
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if (!array_key_exists($requestContent["type"], Course::TYPES_ARRAY)) {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => "Поле \"Тип\" не должно иметь значение: \"rent\", \"buy\" или \"free\"."
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if (!is_numeric($requestContent["price"])) {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => "Поле \"Стоимость курса\" можно вводить только цифры."
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if ($requestContent["price"] !== 0 && $requestContent["type"] === "free") {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => 'Курс с типом "Бесплатный" не может иметь стоимость.'
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if ($requestContent["price"] <= 0 && $requestContent["type"] !== "free") {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => 'Курс с типом "Аренда" или "Покупка" не может нулевую или отрицательную стоимость.'
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        $existedCourse = $isEdit ? $courseRepository->findOneBy(['characterCode' => $previousCode])
            :  $courseRepository->findOneBy(['characterCode' => $requestContent["code"]]);
        if (!is_null($existedCourse) && !$isEdit) {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_ACCEPTABLE,
                "message" => 'Курс с таким символьным кодом уже существует.'
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        if (is_null($existedCourse) && $isEdit) {
            $newCodeCourse = $courseRepository->findOneBy(['characterCode' => $requestContent["code"]]);
            if (!is_null($newCodeCourse)) {
                return new JsonResponse([
                    "code" => Response::HTTP_NOT_ACCEPTABLE,
                    "message" => 'Курс с таким символьным кодом уже существует.'
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
            return new JsonResponse([
                "code" => Response::HTTP_NOT_FOUND,
                "message" => 'Курс с таким символьным кодом не существует.'
            ], Response::HTTP_NOT_FOUND);
        }
        return "success";
    }

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
        $response = [];
        foreach ($courses as $course) {
            $response[] = [
                'character_code' => $course->getCharacterCode(),
                'type' => $course->getTypeCode(),
                'price' => $course->getCost()
            ];
        }
        return new JsonResponse($response);
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
        string           $code,
        CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['characterCode' => $code]);
        if (!$course) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с таким символьным кодом не найден.',
            ], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse([
            "character_code" => $course->getCharacterCode(),
            "type" => $course->getTypeCode(),
            "price" => $course->getCost()
        ]);
    }

    /**
     * @Route("/{code}/pay", name="app_course_pay", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses/{code}/pay",
     *     summary="Оплата курса",
     *     description="Запрос на оплату конкретного курса по символьному коду."
     * )
     * @OA\Response(
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
     *     response="406/4",
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
     *     response="406/5",
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
        string                $code,
        CourseRepository      $courseRepository,
        PaymentService        $paymentService,
        TransactionRepository $transactionRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['characterCode' => $code]);
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

        if ($course->getTypeCode() === 'free') {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => 'Этот курс бесплатен и не требует покупки.',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        if ($user->getBalance() < $course->getCost()) {
            $typeOfTransactionText = $course->getTypeCode() === 'rent' ? 'аренды' : 'покупки';
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => "У вас не достаточно средств на счёте для " .
                    "{$typeOfTransactionText} этого курса.",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        try {
            $existedTransaction = $course->getTypeCode() === "buy" ? $transactionRepository
                ->findOneBy(['transactionUser' => $user, 'course' => $course, 'type' => 0]) :
                $transactionRepository->getTransactionOnTypeRentWithMaxDate($course, $user);
            if ($existedTransaction) {
                if ($existedTransaction->getCourse()->getTypeCode() === 'rent'
                    && $existedTransaction->getExpiredAt() > new \DateTimeImmutable('now')
                ) {
                    return new JsonResponse([
                        'code' => Response::HTTP_NOT_ACCEPTABLE,
                        'message' => 'Этот курс уже арендован и длительность аренды ещё не истекла.',
                    ], Response::HTTP_NOT_ACCEPTABLE);
                }
                if ($existedTransaction->getCourse()->getTypeCode() === 'buy') {
                    return new JsonResponse([
                        'code' => Response::HTTP_NOT_ACCEPTABLE,
                        'message' => 'Этот курс уже куплен.',
                    ], Response::HTTP_NOT_ACCEPTABLE);
                }
            }

            $transaction = $paymentService->makePayment($user, $course);
            $response = [
                "success" => true,
                "course_type" => $transaction->getCourse()->getTypeCode(),
            ];
            if ($transaction->getExpiredAt()) {
                $response["expires_at"] = $transaction->getExpiredAt()->format(DATE_ATOM);
            }
            return new JsonResponse($response, Response::HTTP_CREATED);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $exception->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("", name="app_courses_new", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses",
     *     summary="Добавление нового курса",
     *     description="Запрос на добавление нового курса в биллинг."
     * )
     * @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="success",
     *          type="bool",
     *          example="true"
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="403",
     *     description="Ответ при запросе неавторизированным пользователем",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="К этому запросу имеет доступ только пользователь с правами администратора."
     *        ),
     * )
     * )
     * @OA\Response(
     *     response="406/1",
     *     description="Ответ при запросе, когда не добавлено одно из нужных полей",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="В запросе отсутствует поле <поле>."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/2",
     *     description="Ответ при запросе, когда одно из полей не соответсвует размером.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле <поле> не должно быть  длинной более <limit> символов|пустым."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/3",
     *     description="Ответ при запросе, когда символьный код содержит что-то помимо цифр и латиницы.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="В поле Cимвольный код могут содержаться только цифры и латиница."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/4",
     *     description="Ответ при запросе, когда вверён неверный тип курса",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле Тип не должно иметь значение: rent, buy или free."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/5",
     *     description="Ответ при запросе, когда введено неверное значение в поле суммы",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле Стоимость курса можно вводить только цифры."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/6",
     *     description="Ответ при запросе, когда для бесплатного курса ненулевая сумма.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с типом Бесплатный не может иметь стоимость."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/7",
     *     description="Ответ при запросе, когда для у платных курсов нулевая сумма.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с типом Аренда или Покупка не может нулевую или отрицательную стоимость."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/8",
     *     description="Ответ при запросе, когда курс с таким символным кодом существует.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с таким символьным кодом уже существует."
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
    public function new(Request $request, CourseRepository $courseRepository)
    {
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse([
                "code" => Response::HTTP_FORBIDDEN,
                "message" => "К этому запросу имеет доступ только пользователь с правами администратора."
            ], Response::HTTP_FORBIDDEN);
        }

        $requestContent = json_decode($request->getContent(), true);
        $validateResult = $this->validate($requestContent, $courseRepository);
        if($validateResult !== "success"){
            return $validateResult;
        }
        $course = new Course();
        $course
            ->setCharacterCode($requestContent["code"])
            ->setCost($requestContent["price"])
            ->setType(Course::TYPES_ARRAY[$requestContent["type"]])
            ->setName($requestContent["title"]);
        $courseRepository->add($course, true);
        return new JsonResponse([
            "success" => true
        ], Response::HTTP_CREATED);
    }

    /**
     * @Route("/{code}/edit", name="app_courses_edit", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses/{code}/edit",
     *     summary="Обновление курса",
     *     description="Запрос на обновление курса в биллинге."
     * )
     * @OA\Response(
     *     response=200,
     *     description="Ответ при удачном запросе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="success",
     *          type="bool",
     *          example="true"
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="403",
     *     description="Ответ при запросе неавторизированным пользователем",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="К этому запросу имеет доступ только пользователь с правами администратора."
     *        ),
     * )
     * )
     * @OA\Response(
     *     response="406/1",
     *     description="Ответ при запросе, когда не добавлено одно из нужных полей",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="В запросе отсутствует поле <поле>."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/2",
     *     description="Ответ при запросе, когда одно из полей не соответсвует размером.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле <поле> не должно быть  длинной более <limit> символов|пустым."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/3",
     *     description="Ответ при запросе, когда символьный код содержит что-то помимо цифр и латиницы.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="В поле Cимвольный код могут содержаться только цифры и латиница."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/4",
     *     description="Ответ при запросе, когда вверён неверный тип курса",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле Тип не должно иметь значение: rent, buy или free."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/5",
     *     description="Ответ при запросе, когда введено неверное значение в поле суммы",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Поле Стоимость курса можно вводить только цифры."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/6",
     *     description="Ответ при запросе, когда для бесплатного курса ненулевая сумма.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с типом Бесплатный не может иметь стоимость."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/7",
     *     description="Ответ при запросе, когда для у платных курсов нулевая сумма.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с типом Аренда или Покупка не может нулевую или отрицательную стоимость."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="406/8",
     *     description="Ответ при запросе, когда курс с таким символным кодом существует.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с таким символьным кодом уже существует."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="404",
     *     description="Ответ при запросе, когда курс с таким символным кодом не существует.",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="404"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Курс с таким символьным кодом уже существует."
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
    public function edit(string $code, Request $request, CourseRepository $courseRepository)
    {
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse([
                "code" => Response::HTTP_FORBIDDEN,
                "message" => "К этому запросу имеет доступ только пользователь с правами администратора."
            ], Response::HTTP_FORBIDDEN);
        }

        $requestContent = json_decode($request->getContent(), true);
        $validateResult = $this->validate($requestContent, $courseRepository, true, $code);
        if($validateResult !== "success"){
            return $validateResult;
        }
        $course = $courseRepository->findOneBy(['characterCode' => $code]);
        $course
            ->setCharacterCode($requestContent["code"])
            ->setCost($requestContent["price"])
            ->setType(Course::TYPES_ARRAY[$requestContent["type"]])
            ->setName($requestContent["title"]);
        $courseRepository->add($course, true);
        return new JsonResponse([
            "success" => true
        ], Response::HTTP_CREATED);
    }
}
