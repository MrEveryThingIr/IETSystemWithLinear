<?php

namespace App;

enum EconomicInstrumentKind: string
{
    case FiatCurrency = 'fiat_currency';
    case InternalUnit = 'internal_unit';
    case CryptoAsset = 'crypto_asset';
    case Commodity = 'commodity';
    case MarketIndex = 'market_index';
    case PropertyIndex = 'property_index';
    case PhysicalAssetClass = 'physical_asset_class';
    case ServiceUnit = 'service_unit';
}
