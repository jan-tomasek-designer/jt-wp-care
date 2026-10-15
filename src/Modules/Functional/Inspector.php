<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Functional;

/** GET only, same-site targets, bounded body/time/count; no forms, checkout or visual assertions. */
final class Inspector
{
    public function collect(): array
    {
        $authenticated = is_user_logged_in() && current_user_can('manage_options');
        $out = [
            'login_session' => $this->result($authenticated ? 'done' : 'problem', $authenticated
                ? 'Aktuální požadavek má platnou přihlášenou relaci správce. Neověřuje nové přihlášení formulářem ani 2FA.'
                : 'Aktuální požadavek nepotvrdil přihlášenou relaci správce.'),
            'backoffice' => $this->result($authenticated && is_admin() ? 'done' : 'problem', $authenticated && is_admin()
                ? 'Administrace načetla JT WP Care a autorizovaný diagnostický požadavek. Neověřuje ostatní obrazovky ani JavaScript.'
                : 'Aktuální požadavek nepotvrdil dostupnou administraci.'),
        ];
        $targets = $this->targets(); $homeKey = array_key_first($targets); $details = []; $states = [];
        foreach ($targets as $key => $target) {
            $probe = $this->probe($target['url']); $states[$key] = $probe['state'];
            $details[] = $target['label'] . ': ' . $probe['text'];
        }
        $out['frontoffice'] = $this->result($states[$homeKey] ?? 'unknown', 'Úvodní stránka: ' . ($details[0] ?? 'nebyla určena')
            . ' Kontrola nehodnotí vzhled, JavaScript, odkazy ani správnost obsahu.');
        $otherStates = array_values(array_filter($states, static fn($key): bool => $key !== $homeKey, ARRAY_FILTER_USE_KEY));
        $state = !$otherStates ? 'unknown' : (in_array('problem', $otherStates, true) ? 'problem' : (in_array('unknown', $otherStates, true) ? 'unknown' : 'done'));
        $out['basic_pages'] = $this->result($state, implode(' | ', array_slice($details, 1))
            . ($otherStates ? '' : 'Další základní stránka nebyla nalezena.')
            . ' Jde pouze o HTTP/HTML dostupnost bez vizuální a obsahové kontroly.');
        return $out;
    }

    private function targets(): array
    {
        $targets = ['home' => ['label' => 'Úvodní stránka', 'url' => home_url('/')]];
        $ids = [(int) get_option('page_on_front', 0), (int) get_option('page_for_posts', 0)];
        if (function_exists('wc_get_page_id')) { foreach (['shop', 'cart', 'checkout', 'myaccount'] as $page) { $ids[] = (int) wc_get_page_id($page); } }
        foreach (get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 5, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true]) as $id) { $ids[] = (int) $id; }
        foreach (array_unique(array_filter($ids)) as $id) {
            if (count($targets) >= 5) { break; }
            $url = get_permalink($id);
            if (!is_string($url) || !$this->sameSite($url) || untrailingslashit($url) === untrailingslashit(home_url('/'))) { continue; }
            $targets['page-' . $id] = ['label' => wp_strip_all_tags(get_the_title($id) ?: 'Stránka ' . $id), 'url' => $url];
        }
        return $targets;
    }

    private function sameSite(string $url): bool
    {
        $target = wp_parse_url($url); $home = wp_parse_url(home_url('/'));
        return is_array($target) && is_array($home) && in_array($target['scheme'] ?? '', ['http', 'https'], true)
            && strtolower($target['host'] ?? '') === strtolower($home['host'] ?? '')
            && ($target['port'] ?? null) === ($home['port'] ?? null) && !isset($target['user']) && !isset($target['pass']);
    }

    private function probe(string $url): array
    {
        if (!$this->sameSite($url)) { return $this->result('unknown', 'nepovolený cíl'); }
        $response = wp_safe_remote_get($url, ['timeout' => 3, 'redirection' => 3, 'limit_response_size' => 262144,
            'headers' => ['User-Agent' => 'JT-WP-Care/' . \JT\WPCare\Core\Release::VERSION]]);
        if (is_wp_error($response)) { return $this->result('unknown', 'serverový požadavek se nezdařil'); }
        $code = wp_remote_retrieve_response_code($response); $type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        $body = wp_remote_retrieve_body($response);
        if ($code >= 400) { return $this->result('problem', 'HTTP ' . $code); }
        if ($code < 200 || $code >= 400 || !str_contains($type, 'text/html') || !is_string($body) || trim($body) === '') {
            return $this->result('unknown', 'HTTP ' . $code . ', ale HTML odpověď nebyla potvrzena');
        }
        $fatal = preg_match('/(fatal error|critical error|parse error|database connection|maintenance[^<]{0,80}(unavailable|moment))/i', wp_strip_all_tags(substr($body, 0, 262144)));
        if ($fatal) { return $this->result('problem', 'HTTP ' . $code . ', ale stránka obsahuje známku kritické PHP, databázové nebo údržbové chyby'); }
        return $this->result('done', 'HTTP ' . $code . ', HTML odpověď přijata bez rozpoznané kritické chyby');
    }

    private function result(string $state, string $text): array { return compact('state', 'text'); }
}
