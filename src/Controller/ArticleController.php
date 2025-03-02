<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\AddArticleType;
use App\Form\UpdateArticleType;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticleController extends AbstractController
{
    #[Route('/articles', name: 'app_articles', methods:"GET")]
    public function index(ArticleRepository $articleRepository): Response
    {
        $articles = $articleRepository->findBy([], ['id' => 'ASC']);;
        return $this->render('article/index.html.twig', compact('articles'));
    }

    #[Route('/article/{id<[0-9]+>}', name: 'app_articles_show', methods:"GET")]
    public function show(Article $art, ArticleRepository $articleRepository): Response
    {
        $filter = new Criteria();
        $filter->where(Criteria::expr()->neq('id', $art->getId()));

        $articles = $articleRepository->matching($filter);

        return $this->render('article/showArticle.html.twig', compact('art', 'articles'));
    }

    #[Route('/articles/add', name: 'app_articles_add', methods:"GET|POST")]
    public function add(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $art = new Article;
        $form = $this->createForm(AddArticleType::class, $art);
            
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $photoItem = $form->get('photo')->getData(); 

            if($photoItem) {
                $filename = pathinfo($photoItem->getClientOriginalName(), PATHINFO_FILENAME);
                $slug_filename = $slugger->slug($filename);
                $photo_filename = $slug_filename.'-'.uniqid().'.'.$photoItem->guessExtension();
        
                try {
                    $photoItem->move(
                        $this->getParameter('upload_img_directory'),
                        $photo_filename
                    );
                } catch (FileException $e) {
                    return $this->json([
                        'error' => "Le fichier n'a pas été déplacé",
                    ]);
                }

                $art->setPhoto($photo_filename);
            }
            
            $em->persist($art);
            $em->flush();
            return $this->redirectToRoute('app_articles');

        }

        return $this->render('article/addArticle.html.twig', [
            'addArticleForm' => $form->createView()
        ]);
    }

    #[Route('/article/update/{id<[0-9]+>}', name: 'app_articles_update')]
    public function update(Article $art, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UpdateArticleType::class, $art);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            
            return $this->redirectToRoute('app_articles');
        }

        return $this->render('article/updateArticle.html.twig', [
            'art' => $art,
            'updateArticleForm' => $form->createView()
        ]);
    }


    #[Route('/article/delete/{id<[0-9]+>}', name: 'app_articles_delete', methods:"DELETE")]
    public function delete(Article $art, Request $request,EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('artDelete_' . $art->getId(), $request->request->get('csrf_token'))) {
            $em->remove($art);
            $em->flush();
        }
        return $this->redirectToRoute('app_articles');
    }
}
