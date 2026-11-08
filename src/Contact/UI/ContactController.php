<?php

declare(strict_types=1);

namespace App\Contact\UI;

use App\Contact\Domain\ContactMessageNotSentException;
use App\Contact\Domain\ContactMessageRejectedException;
use App\Contact\Domain\ContactMessageSender;
use App\Contact\Domain\InquiredProduct;
use App\Contact\Domain\InquiredProductRepository;
use App\Contact\UI\Form\ContactFormData;
use App\Contact\UI\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class ContactController extends AbstractController
{
    /**
     * `?produit=<slug>` fait du message une demande de renseignement sur ce produit, quand l'API le connaît.
     */
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ContactMessageSender $sender, InquiredProductRepository $products): Response
    {
        $slug = $request->query->getString('produit');
        $product = '' === $slug ? null : $products->find($slug);
        $data = self::formData($product);
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderPage($form, $product);
        }

        try {
            $sender->send($data->toMessage());
        } catch (ContactMessageRejectedException $exception) {
            foreach ($exception->violations as $violation) {
                $target = $form->has($violation['field']) ? $form->get($violation['field']) : $form;
                $target->addError(new FormError($violation['message']));
            }

            return $this->renderPage($form, $product, status: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ContactMessageNotSentException $exception) {
            return $this->renderPage($form, $product, $exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $success = null === $data->product ? 'Merci, votre message a bien été envoyé.' : 'Votre demande de renseignement a bien été envoyée.';

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('contact/success.stream.html.twig', [
                'form' => $this->createForm(ContactType::class, self::formData($product)),
                'success' => $success,
            ]);
        }

        $this->addFlash('contact_success', $success);

        return $this->redirectToRoute('contact', null === $product ? [] : ['produit' => $product->slug], Response::HTTP_SEE_OTHER);
    }

    private static function formData(?InquiredProduct $product): ContactFormData
    {
        $data = new ContactFormData();
        $data->product = $product?->slug;

        return $data;
    }

    /**
     * @param FormInterface<ContactFormData> $form
     */
    private function renderPage(FormInterface $form, ?InquiredProduct $product, ?string $sendError = null, int $status = Response::HTTP_OK): Response
    {
        return $this->render('contact/index.html.twig', [
            'form' => $form,
            'product' => $product,
            'send_error' => $sendError,
        ], new Response(status: $status));
    }
}
