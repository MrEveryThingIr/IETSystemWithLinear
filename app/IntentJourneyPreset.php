<?php

namespace App;

enum IntentJourneyPreset: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Rent = 'rent';
    case RentOut = 'rent_out';
    case NeedService = 'need_service';
    case OfferService = 'offer_service';
    case Hire = 'hire';
    case FindWork = 'find_work';
    case SeekCapital = 'seek_capital';
    case OfferCapital = 'offer_capital';
    case SeekCollaboration = 'seek_collaboration';
    case OfferCollaboration = 'offer_collaboration';
    case Other = 'other';

    public function kind(): ProfileIntentKind
    {
        return match ($this) {
            self::Buy,
            self::Rent,
            self::NeedService,
            self::Hire,
            self::SeekCapital,
            self::SeekCollaboration,
            self::Other => ProfileIntentKind::Need,
            self::Sell,
            self::RentOut,
            self::OfferService,
            self::FindWork,
            self::OfferCapital,
            self::OfferCollaboration => ProfileIntentKind::Offer,
        };
    }

    public function subjectKind(): ProfileIntentSubjectKind
    {
        return match ($this) {
            self::Buy,
            self::Sell => ProfileIntentSubjectKind::Good,
            self::Rent,
            self::RentOut => ProfileIntentSubjectKind::Property,
            self::NeedService,
            self::OfferService,
            self::Hire,
            self::FindWork => ProfileIntentSubjectKind::Service,
            self::SeekCapital,
            self::OfferCapital => ProfileIntentSubjectKind::Capital,
            self::SeekCollaboration,
            self::OfferCollaboration => ProfileIntentSubjectKind::Collaboration,
            self::Other => ProfileIntentSubjectKind::Other,
        };
    }

    public function arrangementKind(): ProfileIntentArrangementKind
    {
        return match ($this) {
            self::Buy,
            self::Sell => ProfileIntentArrangementKind::OwnershipTransfer,
            self::Rent,
            self::RentOut => ProfileIntentArrangementKind::TemporaryUse,
            self::NeedService,
            self::OfferService,
            self::Hire,
            self::FindWork => ProfileIntentArrangementKind::Service,
            self::SeekCapital,
            self::OfferCapital => ProfileIntentArrangementKind::Financing,
            self::SeekCollaboration,
            self::OfferCollaboration => ProfileIntentArrangementKind::Collaboration,
            self::Other => ProfileIntentArrangementKind::Other,
        };
    }

    /** @return list<ProfileIntentSubjectKind> */
    public function subjectKinds(): array
    {
        return match ($this) {
            self::Buy,
            self::Sell,
            self::Rent,
            self::RentOut => [
                ProfileIntentSubjectKind::Property,
                ProfileIntentSubjectKind::Good,
                ProfileIntentSubjectKind::Other,
            ],
            self::Other => ProfileIntentSubjectKind::cases(),
            default => [$this->subjectKind()],
        };
    }

    public function isManual(): bool
    {
        return $this === self::Other;
    }
}
