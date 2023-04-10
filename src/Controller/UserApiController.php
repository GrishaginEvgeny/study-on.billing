<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\LcobucciJWTEncoder;

/**
 * @Route("/api/v1")
 */

class UserApiController extends AbstractController
{

    private ValidatorInterface $validator;

    private UserPasswordHasherInterface $hasher;

    private JWTTokenManagerInterface $JWTTokenManager;

    public function __construct(
        ValidatorInterface $validator,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $JWTTokenManager
    ) {
        $this->validator = $validator;
        $this->hasher = $hasher;
        $this->JWTTokenManager = $JWTTokenManager;
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
     *          description="Пароль пользователя соответствующий данному регулярному выражению: /(?=.*[0-9])(?=.*[.!@#$%^&*])(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z!@#$%^&*.]+$/.",
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
     *          property="message",
     *          type="string",
     *          example="Ошибка регистрации"
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
     */
    public function register(Request $request, UserRepository $userRepository): JsonResponse {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $this->validator->validate($userDto);
        if(count($errors) > 0) {
            $jsonedError = [];
            foreach ($errors as $error){
                $jsonedError[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'error_description' => 'Ошибка регистрации',
                'errors' => $jsonedError,
            ],Response::HTTP_BAD_REQUEST);
        }

        if($userRepository->findOneBy(['email' => $userDto->username])){
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'error_description' => 'Ошибка регистрации',
                'errors' => [
                    "username" => 'Пользователь с таким E-mail уже зарегистрирован.'
                ],
            ],Response::HTTP_BAD_REQUEST);
        }

        $newUser = User::fromDTO($userDto);
        $newUser->setPassword($this->hasher->hashPassword($newUser, $userDto->password));
        $newUser->setRoles(['ROLE_USER']);
        $userRepository->add($newUser, true);

        return new JsonResponse([
            'token' => $this->JWTTokenManager->create($newUser),
        ], Response::HTTP_CREATED);
    }

    /**
     * @Security(name="Bearer")
     * @Route("/users/current", name="app_user_api_current_user", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/users/current",
     *     summary="Получение актуального авторизированного пользоватея",
     *     description="Запрос на получение актуального авторизированного пользователя. При выполнении запроса будучи неавторизированным будет возвращена ошибка.
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
     * @OA\Tag(name="UserApi")
     */
    public function currentUser(): JsonResponse
    {
        if(!$this->getUser()){
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                "message" => "JWT Token not found"
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'code' => Response::HTTP_OK,
            'username' => $this->getUser()->getUserIdentifier(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }
}
