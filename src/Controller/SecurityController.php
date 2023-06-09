<?php

namespace App\Controller;

use App\Entity\Posts;
use App\Entity\User;
use App\Form\InscriptionUserType;
use App\Repository\AboutMeRepository;
use App\Repository\PostsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/', name: "app_home")]

    public function index(AboutMeRepository $aboutMeRepository, PostsRepository $postsRepository,  PaginatorInterface $paginator, Request $request): Response
    {
        $aboutMe = $aboutMeRepository->findAll();
        $posts = $postsRepository->findBy([], ['id' => 'DESC']);

        // Trouver le dernier article visible
        $lastVisibleArticle = null;
        foreach ($posts as $post) {
            if ($post->isVisible()) {
                $lastVisibleArticle = $post;
                break;
            }
        }

        // Supprimer le dernier article de la liste des articles
        array_shift($posts);

        // Pagination
        $pagination = $paginator->paginate(
            $posts,
            $request->query->get('page', 1),
            6 // limite par page
        );

        return $this->render('public/index.html.twig', [
            'aboutMe' => $aboutMe,
            'lastVisibleArticle' => $lastVisibleArticle,
            'posts' => $posts,
            'pagination' => $pagination,
        ]);
    }


    //Montrer un post public
    #[Route('post/{slug?}', name: 'app_post_read', methods: ['GET'])]
    public function showPost(Posts $post): Response
    {

        $user = $this->getUser();
        return $this->render('/public/post.html.twig', [
            'post' => $post,
            'slug' => $post->getSlug(),
            'user' => $user,
        ]);
    }

    #[Route('inscription', name: "app_inscription")]
    public function register(Request $request, UserPasswordHasherInterface $passwordEncoder, EntityManagerInterface $entityManager)
    {
        // 1) build the form
        $user = new User();

        $form = $this->createForm(InscriptionUserType::class, $user);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $submittedToken = $request->request->get('token');

            // 'delete-item' is the same value used in the template to generate the token
            if ($this->isCsrfTokenValid('delete-item', $submittedToken)) {
                // ... do something, like deleting an object
            }

            // 3) Encode the password (you could also do this via Doctrine listener)
            $password = $passwordEncoder->hashPassword($user, $user->getPassword());
            $user->setPassword($password);

            // 4) save the User!

            $entityManager->persist($user);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the user

            return $this->redirectToRoute('app_home');
        }

        return $this->render('public/inscription.html.twig', ['form' => $form->createView()]);
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('public/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
