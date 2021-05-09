<?php declare(strict_types=1);

namespace App\Service\Notification;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Entity\PostComment;
use App\Entity\PostCommentCreatedNotification;
use App\Entity\PostCommentDeletedNotification;
use App\Factory\MagazineFactory;
use App\Repository\MagazineSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;
use function count;

class PostCommentNotificationManager
{
    use NotificationTrait;

    public function __construct(
        private MagazineSubscriptionRepository $repository,
        private IriConverterInterface $iriConverter,
        private MagazineFactory $magazineFactory,
        private PublisherInterface $publisher,
        private Environment $twig,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function sendCreated(PostComment $comment): void
    {
        $subs      = $this->getUsersToNotify($this->repository->findNewPostSubscribers($comment->post));
        $followers = [];

        $usersToNotify = $this->merge($subs, $followers);

        $this->notifyMagazine(new PostCommentCreatedNotification($comment->user, $comment));
        if (!count($usersToNotify)) {
            return;
        }

        foreach ($usersToNotify as $subscriber) {
            $notification = new PostCommentCreatedNotification($subscriber, $comment);
            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }

    private function notifyMagazine(PostCommentCreatedNotification $notification): void
    {
        try {
            $iri = $this->iriConverter->getIriFromItem($this->magazineFactory->createDto($notification->getComment()->magazine));

            $update = new Update(
                $iri,
                $this->getResponse($notification)
            );

            ($this->publisher)($update);

        } catch (Exception $e) {
        }
    }

    private function getResponse(PostCommentCreatedNotification $notification): string
    {
        return json_encode(
            [
                'op'   => 'PostCommentCreatedNotification',
                'id'   => $notification->getComment()->getId(),
                'data' => [],
                'html' => $this->twig->render('_layout/_toast.html.twig', ['notification' => $notification]),
            ]
        );
    }

    public function sendDeleted(PostComment $comment): void
    {
        $notification = new PostCommentDeletedNotification($comment->getUser(), $comment);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
