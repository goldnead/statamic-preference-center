<?php

/*
 * Geduzt, wie der Rest dieser Addon-Familie. `statamic-marketing` und
 * `statamic-notifications` duzen in ihren deutschen Sätzen seit jeher; dieses
 * Paket war das einzige, das siezte — was auf einer Installation auffällt, wo
 * dieselbe Person eine geduzte Bestätigungsmail und einen gesiezten Link zu den
 * Einstellungen bekommt. Die Sätze sind dabei unverändert geblieben, getauscht
 * ist nur die Anrede.
 */

return [

    'magic_link_subject' => 'Dein Link zu den E-Mail-Einstellungen',
    'magic_link_greeting' => 'Hallo,',
    'magic_link_body' => 'hier ist der angeforderte Link zu deinen E-Mail-Einstellungen. Er gilt :minutes Minuten.',
    'magic_link_button' => 'E-Mail-Einstellungen öffnen',
    'magic_link_fallback' => 'Wenn der Knopf nicht funktioniert, kopiere diese Adresse in deinen Browser:',
    'magic_link_several' => 'Diese Adresse ist bei uns unter mehreren Marken bekannt. Pro Marke gibt es einen Link, und jeder öffnet nur die Einstellungen dieser Marke.',
    'magic_link_ignore' => 'Wenn du das nicht angefordert hast, ignoriere diese Nachricht. Ohne den Link passiert nichts.',

];
