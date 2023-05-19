<?php

namespace App\Controller;

use App\DTO\CourseDTO;
use App\DTO\CourseEditDTO;
use App\DTO\PayDTO;
use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Services\PaymentService;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/api/v1/courses")
 */
class CourseApiController extends AbstractController
{
    private CourseRepository $courseRepository;

    private PaymentService $paymentService;

    private SerializerInterface $serializer;

    private ValidatorInterface $validator;

    private TranslatorInterface $translator;

    public function __construct(
        CourseRepository $courseRepository,
        PaymentService $paymentService,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TranslatorInterface $translator
    ) {
        $this->serializer = $serializer;
        $this->courseRepository = $courseRepository;
        $this->paymentService = $paymentService;
        $this->validator = $validator;
        $this->translator = $translator;
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
     *          property="course_code",
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
    public function courses(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();
        $response = [];
        foreach ($courses as $course) {
            $response[] = [
                'course_code' => $course->getCharacterCode(),
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
     *          property="course_code",
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
    public function course(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['characterCode' => $code]);
        if (!$course) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => $this->translator->trans('errors.course.doesnt_exist', [], 'validators')
            ], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse([
            "course_code" => $course->getCharacterCode(),
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
     *     response=406,
     *     description="Ответ при неудачном прохождении валидации",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *              @OA\Property(
     *                  type="string",
     *                  property="error_field"
     *              )
     *          )
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
    public function pay(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['characterCode' => $code]);
        $user = $this->getUser();

        if (!$course) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => $this->translator->trans('errors.course.doesnt_exist', [], 'validators')
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => $this->translator->trans('errors.user.doesnt_auth', [], 'validators')
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payDTO = new PayDTO($user, $course);
        $errorsFromDto = $this->validator->validate($payDTO);
        if (count($errorsFromDto) > 0) {
            $errors = [];
            foreach ($errorsFromDto as $error) {
                $errors[] = $this->translator->trans($error->getMessage(), [], 'validators');
            }
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'errors' => $errors,
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $transaction = $this->paymentService->makePayment($user, $course);
        $response = [
            "success" => true,
            "course_type" => $transaction->getCourse()->getTypeCode(),
        ];
        if ($transaction->getExpiredAt()) {
            $response["expires_at"] = $transaction->getExpiredAt()->format(DATE_ATOM);
        }
        return new JsonResponse($response, Response::HTTP_CREATED);
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
     *     response=406,
     *     description="Ответ при неудачном прохождении валидации",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *              @OA\Property(
     *                  type="string",
     *                  property="error_field"
     *              )
     *          )
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
    public function new(Request $request)
    {
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse([
                "code" => Response::HTTP_FORBIDDEN,
                "message" => $this->translator->trans('errors.user.access_denied', [], 'validators')
            ], Response::HTTP_FORBIDDEN);
        }
        $courseDTO = $this->serializer
            ->deserialize($request->getContent(), CourseDTO::class, 'json');
        $errorsFromDto = $this->validator
            ->validate($courseDTO);
        if (count($errorsFromDto) > 0) {
            $errors = [];
            foreach ($errorsFromDto as $error) {
                $errors[$error->getPropertyPath()] = $this->translator->trans($error->getMessage(), [], 'validators');
            }
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'errors' => $errors,
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        $course = new Course();
        $course
            ->setCharacterCode($courseDTO->code)
            ->setCost($courseDTO->price)
            ->setType(Course::TYPES_ARRAY[$courseDTO->type])
            ->setName($courseDTO->title);
        $this->courseRepository->add($course, true);
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
     *     response=406,
     *     description="Ответ при неудачном прохождении валидации",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="406"
     *        ),
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *              @OA\Property(
     *                  type="string",
     *                  property="error_field"
     *              )
     *          )
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
    public function edit(string $code, Request $request)
    {
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse([
                "code" => Response::HTTP_FORBIDDEN,
                "message" => $this->translator->trans('errors.user.access_denied', [], 'validators')
            ], Response::HTTP_FORBIDDEN);
        }

        $course = $this->courseRepository->findOneBy(['characterCode' => $code]);
        if (is_null($course)) {
            return new JsonResponse([
                "code" => Response::HTTP_NOT_FOUND,
                "message" => $this->translator->trans('errors.course.doesnt_exist', [], 'validators')
            ], Response::HTTP_NOT_FOUND);
        }

        $courseDTO = $this->serializer
            ->deserialize($request->getContent(), CourseDTO::class, 'json');
        $errorsFromDto = $this->validator->validate($courseDTO);
        if (count($errorsFromDto) > 0) {
            $errors = [];
            foreach ($errorsFromDto as $error) {
                $errors[$error->getPropertyPath()] = $this->translator->trans($error->getMessage(), [], 'validators');
            }
            if (
                !(count($errors) === 1 &&
                    in_array(
                        $this->translator->trans('errors.course.doesnt_exist', [], 'validators'),
                        $errors,
                        true
                    ) &&
                $code === $courseDTO->code)
            ) {
                return new JsonResponse([
                    'code' => Response::HTTP_NOT_ACCEPTABLE,
                    'errors' => $errors,
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
        }

        $course
            ->setCharacterCode($courseDTO->code)
            ->setCost($courseDTO->price)
            ->setType(Course::TYPES_ARRAY[$courseDTO->type])
            ->setName($courseDTO->title);
        $this->courseRepository->add($course, true);
        return new JsonResponse([
            "success" => true
        ], Response::HTTP_CREATED);
    }
}
