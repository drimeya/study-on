<?php

namespace App\Controller;

use App\Dto\BillingCourseResponse;
use App\Entity\Course;
use App\Exception\BillingApiException;
use App\Exception\BillingUnavailableException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses')]
final class CourseController extends AbstractController
{
    // -------------------------------------------------------------------------
    // Список курсов
    // -------------------------------------------------------------------------

    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, BillingClient $billingClient, SessionInterface $session): Response
    {
        $courses = $courseRepository->findAll();
        /** @var array<string, BillingCourseResponse> $billingCourses */
        $billingCourses = [];
        /** @var array<string, \App\Dto\BillingTransactionResponse> $paymentStatuses */
        $paymentStatuses = [];
        $billingError = false;

        try {
            foreach ($billingClient->getCourses() as $bc) {
                $billingCourses[$bc->code] = $bc;
            }

            $token = $session->get('billing_token');
            if ($token) {
                $transactions = $billingClient->getTransactions($token, [
                    'type' => 'payment',
                    'skip_expired' => 1,
                ]);
                foreach ($transactions as $t) {
                    if ($t->courseCode !== null) {
                        $paymentStatuses[$t->courseCode] = $t;
                    }
                }
            }
        } catch (BillingUnavailableException) {
            $billingError = true;
        }

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
            'billingCourses' => $billingCourses,
            'paymentStatuses' => $paymentStatuses,
            'billingError' => $billingError,
        ]);
    }

    // -------------------------------------------------------------------------
    // Создание курса
    // -------------------------------------------------------------------------

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // Просмотр курса
    // -------------------------------------------------------------------------

    #[Route('/{code}', name: 'app_course_show', methods: ['GET'])]
    public function show(string $code, CourseRepository $courseRepository, BillingClient $billingClient, SessionInterface $session): Response
    {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            throw new NotFoundHttpException('Курс не найден');
        }

        $lessons = $course->getLessons()->toArray();
        usort($lessons, fn ($a, $b) => $a->getSort() <=> $b->getSort());

        $billingCourse = null;
        $paymentInfo = null;
        $balance = null;
        $billingError = false;

        try {
            $billingCourse = $billingClient->getCourse($code);
            $token = $session->get('billing_token');

            if ($token && $billingCourse !== null && !$billingCourse->isFree()) {
                $transactions = $billingClient->getTransactions($token, [
                    'type' => 'payment',
                    'course_code' => $code,
                    'skip_expired' => 1,
                ]);
                if (!empty($transactions)) {
                    $paymentInfo = $transactions[0];
                }

                $userData = $billingClient->getCurrentUser($token);
                $balance = $userData->balance;
            }
        } catch (BillingUnavailableException) {
            $billingError = true;
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $lessons,
            'billingCourse' => $billingCourse,
            'paymentInfo' => $paymentInfo,
            'balance' => $balance,
            'billingError' => $billingError,
        ]);
    }

    // -------------------------------------------------------------------------
    // Оплата курса
    // -------------------------------------------------------------------------

    #[Route('/{code}/pay', name: 'app_course_pay', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function pay(string $code, SessionInterface $session, BillingClient $billingClient): Response
    {
        $token = $session->get('billing_token');

        try {
            $billingClient->payCourse($code, $token);
            $this->addFlash('success', 'Курс успешно оплачен');
        } catch (BillingApiException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (BillingUnavailableException) {
            $this->addFlash('error', 'Сервис временно недоступен. Попробуйте позже.');
        }

        return $this->redirectToRoute('app_course_show', ['code' => $code]);
    }

    // -------------------------------------------------------------------------
    // Редактирование / удаление
    // -------------------------------------------------------------------------

    #[Route('/{code}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(string $code, Request $request, CourseRepository $courseRepository, EntityManagerInterface $entityManager): Response
    {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            throw new NotFoundHttpException('Курс не найден');
        }

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
