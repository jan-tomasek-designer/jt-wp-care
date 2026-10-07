<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Security;

/** Conservative advice: this is not proof that all XML-RPC consumers are known. */
final class XmlRpcPolicy
{
    public static function advice(array $known, string $externalUse, $localAuth): string
    {
        if ($known !== [] || $externalUse === 'used') {
            return 'Nalezená nebo potvrzená návaznost. Nedoporučujeme plošné vypnutí; ověřte konkrétní integraci.';
        }
        if ($externalUse !== 'unused') {
            return 'Externí použití není ověřené. Správce musí potvrdit vzdálené publikování, mobilní klienty a vlastní integrace.';
        }
        if (!is_bool($localAuth)) {
            return 'Lokální stav není jednoznačný. Nejprve doplňte ověření; vypnutí nelze zatím doporučit.';
        }
        if (!$localAuth) {
            return 'Lokálně omezené přihlašované metody. Ověřte zbývající XML-RPC a blokování z internetu; neopakujte zásah naslepo.';
        }
        return 'Žádná známá návaznost a správce potvrdil nepotřebnost. Lze zvážit ruční vypnutí s plánem návratu a následnými funkčními testy. Nejde o záruku úplnosti detekce.';
    }
}
