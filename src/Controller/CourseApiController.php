<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Services\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/v1/courses")
 */
class CourseApiController extends AbstractController
{
    /**
     * @Route("", name="app_courses", methods={"GET"})
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function courses(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();
        $arrayedCourses = [];
        foreach ($courses as $course){
            array_push($arrayedCourses,[
                'character_code' => $course->getCharacterCode(),
                'type' => $course->getType(),
                'cost' => $course->getCost()
            ]);
        }
        return new JsonResponse($arrayedCourses);
    }

    /**
     * @Route("/{code}", name="app_course", methods={"GET"})
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
            "code"=> $course->getCharacterCode(),
            "type"=> $course->getStringedType(),
            "price"=> $course->getCost()
        ]);
    }

    /**
     * @Route("/{code}/pay", name="app_course_pay", methods={"POST"})
     */
    public function pay(
        string $code,
        CourseRepository $courseRepository,
        PaymentService $paymentService): JsonResponse{
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
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Вы не авторизованы',
            ], Response::HTTP_FORBIDDEN);
        }

        if($course->getStringedType() == 'free') {
            return new JsonResponse([
                'code' => Response::HTTP_OK,
                'message' => 'Этот курс бесплатен и не требует покупки.',
            ], Response::HTTP_OK);
        }

        if($user->getBalance() < $course->getCost()) {
            $textForResponse = $course->getStringedType() == 'rent' ? 'аренды' : 'покупки';
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => "У вас не достаточно средств на счёте для 
                {$textForResponse} этого курса",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        try {
            $transaction = $paymentService->makePayment($user, $course);
            $responseArray = [
                "success" => true,
                "course_type"=> $transaction->getCourse()->getStringedType(),
            ];
            if($transaction->getValidTo()){
                $responseArray["expires_at"] = $transaction->getValidTo()->format(DATE_ISO8601);
            }
            return new JsonResponse($responseArray,Response::HTTP_OK);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $exception->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
