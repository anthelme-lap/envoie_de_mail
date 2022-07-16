<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Services\JWTService;
use App\Services\SendEmailRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
    Request $request, 
    UserPasswordHasherInterface $userPasswordHasher, 
    EntityManagerInterface $entityManager, 
    SendEmailRegisterService $sendEmailRegisterService,
    JWTService $jwt): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            // On génère le jwt de l'utilisateur
            // On crée le header
            $header = [
                'typ' => 'JWT',
                'alg' => 'HS256'
            ];

            // On crée le payload
            $payload = [
                'user_id' => $user->getId()
            ];

            // On génère le token
            $token = $jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
            
            // On envoie le mail
            $sendEmailRegisterService->send(
                'aouekoffi88@gmail.com',
                $user->getEmail(),
                'Activation du compte sur le site 2',
                'register',
                ['user'=> $user,'token'=> $token]
            );
            return $this->redirectToRoute('app_login');
            
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/{token}', name: 'verify_user')]
    public function verifyUser($token, JWTService $jwt, UserRepository $userRepository,EntityManagerInterface $em): Response
    {
        // On vérifie si le token est valide, n'a pas expiré et n'est pas modifié
        if($jwt->isValid($token) && !$jwt->isExpired($token) )
        {
            // On récupère le payload
            $payload = $jwt->getPayload($token);

            // On récupère le user du token
            $user = $userRepository->find($payload['user_id']);

            // On vérifie que l'utilisateur existe et n'as pas encore activé son compte
            if($user && !$user->isIsVerified())
            {
                $user->setIsVerified(true);
                $em->flush($user);
                
                dd('Token valide');
                $this->addFlash('success', 'Token invalide');
                return $this->redirectToRoute('app_home');
            }
        }

        // Si un probleme se pose dans le token
        dd('Token invalide');
        $this->addFlash('success', 'Token invalide');
        return $this->redirectToRoute('app_home');
    }

    #[ROUTE('/renvoiverif', name: 'renvoie_verif')]
    public function resendEmail(JWTService $jwt, SendEmailRegisterService $sendEmailRegisterService, UserRepository $userRepository): Response
    {
        // On vérifie si l'utilisateur est connecté
        $user = $this->getUser();

        if(!$user){
            $this->addFlash('danger', 'Vous devez vous connecter avant d\'accéder à cette page');
            return $this->redirectToRoute('app_login');
        }

        if($user->isIsverifed()){
            $this->addFlash('warning', 'Votre compte est déjà activé');
            return $this->redirectToRoute('app_home');
        }

        // On génère le jwt de l'utilisateur
        // On crée le header
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        // On crée le payload
        $payload = [
            'user_id' => $user->getId()
        ];

        // On génère le token
        $token = $jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
        
        // On envoie le mail
        $sendEmailRegisterService->send(
            'aouekoffi88@gmail.com',
            $user->getEmail(),
            'Activation du compte sur le site 2',
            'register',
            ['user'=> $user,'token'=> $token]
        );
        $this->addFlash('success', 'Email de vérification envoyé');
        return $this->redirectToRoute('app_home');
    }
}
