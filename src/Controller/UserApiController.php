<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\Entity\User;
use App\ErrorTemplate\ErrorTemplate;
use App\Repository\UserRepository;
use App\Services\PaymentService;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/api/v1")
 */
class UserApiController extends AbstractController
{
    private ValidatorInterface $validator;

    private UserPasswordHasherInterface $hasher;

    private JWTTokenManagerInterface $JWTTokenManager;

    private UserRepository $userRepository;

    private RefreshTokenGeneratorInterface $refreshTokenGenerator;

    private RefreshTokenManagerInterface $refreshTokenManager;

    private PaymentService $paymentService;

    private TranslatorInterface $translator;

    public function __construct(
        ValidatorInterface $validator,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $JWTTokenManager,
        UserRepository $userRepository,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService,
        TranslatorInterface $translator
    ) {
        $this->validator = $validator;
        $this->hasher = $hasher;
        $this->JWTTokenManager = $JWTTokenManager;
        $this->userRepository = $userRepository;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->paymentService = $paymentService;
        $this->translator = $translator;
    }

    /**
     * @Route("/auth", name="app_user_api_auth", methods={"POST"})
     *
     *
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="Аутентификация пользователя",
     *     description="Запрос на аутентификацию пользователя, обрабатываемый бандлом.
    Эта функция возвращает JWT-токен при успешной авторизации или ошибки при неудачной."
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email",
     *          example="test@test.com",
     *        ),
     *        @OA\Property(
     *           property="password",
     *          type="string",
     *          description="Пароль",
     *          example="ABc!33113a",
     *        ),
     *     )
     *)
     *
     * @OA\Response(
     *     response=200,
     *     description="Ответ при удачной аутентификации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *         @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ответ при неудачной аутентификации",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
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
     * @OA\Tag(name="UserApi")
     */
    public function auth()
    {
    }

    /**
     * @Route("/register", name="app_user_api_register", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/register",
     *     summary="Регистрация пользователя",
     *     description="Запрос на регистрацию пользователя, написанный вручную.
    Эта функция возвращает JWT-токен при успешной регистрации или ошибки с кодом и описанием при неудачной."
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email",
     *          example="test@test.com",
     *        ),
     *        @OA\Property(
     *           property="password",
     *          type="string",
     *          description="Пароль пользователя соответствующий данному регулярному выражению:
    /(?=.*[0-9])(?=.*[.!@#$%^&*])(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z!@#$%^&*.]+$/.",
     *          example="ABc!33113a",
     *        ),
     *     )
     *)
     *
     * @OA\Response(
     *     response=201,
     *     description="Ответ при удачной регистрации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="Ответ при неудачной регистрации, если не пройдена валидация при регистрации",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="400"
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
     * @OA\Tag(name="UserApi")
     * @throws \Doctrine\DBAL\Exception
     */
    public function register(Request $request): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), UserDTO::class, 'json');
        $errorsFromDto = $this->validator->validate($userDto);
        if (count($errorsFromDto) > 0) {
            $errors = [];
            foreach ($errorsFromDto as $error) {
                $errors[$error->getPropertyPath()] = $this->translator->trans($error->getMessage(), [], 'validators');
            }
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $userDto->username])) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => [
                    "username" => $this->translator->trans('errors.user.email.non_unique', [], 'validators')
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $newUser = User::fromDTO($userDto);
        $newUser->setPassword($this->hasher->hashPassword($newUser, $userDto->password));
        $newUser->setRoles(['ROLE_USER']);
        $this->userRepository->add($newUser, true);
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $newUser,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $this->refreshTokenManager->save($refreshToken);
        $this->paymentService->makeDeposit($newUser, $_ENV['BASE_BALANCE']);


        return new JsonResponse([
            'token' => $this->JWTTokenManager->create($newUser),
            'refresh_token' => $refreshToken->getRefreshToken(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @Security(name="Bearer")
     * @Route("/users/current", name="app_user_api_current_user", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/users/current",
     *     summary="Получение актуального авторизированного пользоватея",
     *     description="Запрос на получение актуального авторизированного пользователя.
          При выполнении запроса будучи неавторизированным будет возвращена ошибка.
    При выполнении запроса при авторизированном пользователе будет возвращена информация о пользователе."
     * )
     * @OA\Response(
     *     response="200",
     *     description="Ответ при запросе авторизированным пользователем",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string"
     *          )
     *        ),
     *        @OA\Property(
     *          property="balance",
     *          type="number",
     *          format="float"
     *        )
     * )
     * )
     *
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
     *          example="JWT Token not found"
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
     * @OA\Tag(name="UserApi")
     */
    public function currentUser(): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                "message" => $this->translator->trans('errors.user.jwt_token_not_found', [], 'validators')
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'username' => $this->getUser()->getUserIdentifier(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }

    /**
     * @Route("/token/refresh", name="app_api_refresh_token", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/v1/token/refresh",
     *     summary="Обновление истёкших JWT токенов пользователей.",
     *     description="Запрос обновляет JWT-токен пользователя, если он истёк.
     * Если запрос происходит от пользователя, который неавторизирован, то будет сообщение о неверных данных."
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *          description="Токен обновления пользвателя",
     *          example="some_token",
     *        ),
     *     )
     *)
     * @OA\Response(
     *     response=200,
     *     description="Успешный запрос на обновление токена",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="401/1",
     *     description="Ответ при неудачном обновлении",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="401/2",
     *     description="Ответ при неудачном обновлении",
     *     @OA\JsonContent(
     *       @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Missing JWT refresh token."
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
     * @Security(name="Bearer")
     * @OA\Tag(name="UserApi")
     */
    public function refresh()
    {
    }
}
