<?php

// Generate a random key and save it if not provided
$key = env('APP_KEY', 'base64:' . base64_encode(random_bytes(32)));
if (empty(env('APP_KEY'))) {
    putenv("APP_KEY={$key}");

    if (!file_exists($envPath = dirname(__DIR__) . '/.env')) {
        file_put_contents($envPath, "APP_KEY={$key}\n");
    } else {
        // Replace APP_KEY in .env file if it exists, otherwise add it
        $env = file_get_contents($envPath);
        $env = "APP_KEY={$key}\n" . preg_replace('/^APP_KEY=.*$(?:\r\n|\n)?/m', '', $env);
        file_put_contents($envPath, $env);
    }
}

return [
    /*
     |--------------------------------------------------------------------------
     | Application Name
     |--------------------------------------------------------------------------
     |
     | This value is the name of your application, which will be used when the
     | framework needs to place the application's name in a notification or
     | other UI elements where an application name needs to be displayed.
     |
     */

    'name' =>
        env('APP_NAME', 'Bokit')
            . (
                env('APP_ENV', 'production') == 'production'
                    ? ''
                    : ' (' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost' ?: 'localhost') . ')'
            ),
    'slogan' => env('APP_SLOGAN', 'Bring On Kitsch Island Time'),
    'logo' => env('APP_LOGO', '/images/logo.png'),
    'version' => env('APP_VERSION', '1.1.0-dev'),

    /*
     |--------------------------------------------------------------------------
     | Application Environment
     |--------------------------------------------------------------------------
     |
     | This value determines the "environment" your application is currently
     | running in. This may determine how you prefer to configure various
     | services the application utilizes. Set this in your ".env" file.
     |
     */

    'env' => env('APP_ENV', isLocal() ? 'local' : 'production'),

    /*
     |--------------------------------------------------------------------------
     | Application Debug Mode
     |--------------------------------------------------------------------------
     |
     | When your application is in debug mode, detailed error messages with
     | stack traces will be shown on every error that occurs within your
     | application. If disabled, a simple generic error page is shown.
     |
     */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
     |--------------------------------------------------------------------------
     | Application URL
     |--------------------------------------------------------------------------
     |
     | This URL is used by the console to properly generate URLs when using
     | the Artisan command line tool. You should set this to the root of
     | the application so that it's available within Artisan commands.
     |
     */

    'url' => env('APP_URL', 'http://localhost'),

    /*
     |--------------------------------------------------------------------------
     | Application Timezone
     |--------------------------------------------------------------------------
     |
     | Here you may specify the default timezone for your application, which
     | will be used by the PHP date and date-time functions. The timezone
     | is set to "UTC" by default as it is suitable for most use cases.
     |
     */

    'timezone' => 'UTC',

    /*
     |--------------------------------------------------------------------------
     | Application Locale Configuration
     |--------------------------------------------------------------------------
     |
     | The application locale determines the default locale that will be used
     | by Laravel's translation / localization methods. This option can be
     | set to any locale for which you plan to have translation strings.
     |
     */

    'locale' => env('APP_LOCALE', 'en'),

    'locales' => ['en', 'fr', 'nl', 'de'],

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'locale_flags' => [
        // 'ad' => 'flags/ad.svg',
        // 'ae' => 'flags/ae.svg',
        // 'af' => 'flags/af.svg',
        // 'ag' => 'flags/ag.svg',
        // 'ai' => 'flags/ai.svg',
        // 'al' => 'flags/al.svg',
        // 'am' => 'flags/am.svg',
        // 'ao' => 'flags/ao.svg',
        // 'aq' => 'flags/aq.svg',
        // 'ar' => 'flags/ar.svg',
        // 'arab' => 'flags/arab.svg',
        // 'as' => 'flags/as.svg',
        // 'asean' => 'flags/asean.svg',
        // 'at' => 'flags/at.svg',
        // 'au' => 'flags/au.svg',
        // 'aw' => 'flags/aw.svg',
        // 'ax' => 'flags/ax.svg',
        // 'az' => 'flags/az.svg',
        // 'ba' => 'flags/ba.svg',
        // 'bb' => 'flags/bb.svg',
        // 'bd' => 'flags/bd.svg',
        // 'be' => 'flags/be.svg',
        // 'bf' => 'flags/bf.svg',
        // 'bg' => 'flags/bg.svg',
        // 'bh' => 'flags/bh.svg',
        // 'bi' => 'flags/bi.svg',
        // 'bj' => 'flags/bj.svg',
        // 'bl' => 'flags/bl.svg',
        // 'bm' => 'flags/bm.svg',
        // 'bn' => 'flags/bn.svg',
        // 'bo' => 'flags/bo.svg',
        // 'bq' => 'flags/bq.svg',
        // 'br' => 'flags/br.svg',
        // 'bs' => 'flags/bs.svg',
        // 'bt' => 'flags/bt.svg',
        // 'bv' => 'flags/bv.svg',
        // 'bw' => 'flags/bw.svg',
        // 'by' => 'flags/by.svg',
        // 'bz' => 'flags/bz.svg',
        // 'ca' => 'flags/ca.svg',
        // 'cc' => 'flags/cc.svg',
        // 'cd' => 'flags/cd.svg',
        // 'cefta' => 'flags/cefta.svg',
        // 'cf' => 'flags/cf.svg',
        // 'cg' => 'flags/cg.svg',
        // 'ch' => 'flags/ch.svg',
        // 'ci' => 'flags/ci.svg',
        // 'ck' => 'flags/ck.svg',
        // 'cl' => 'flags/cl.svg',
        // 'cm' => 'flags/cm.svg',
        // 'cn' => 'flags/cn.svg',
        // 'co' => 'flags/co.svg',
        // 'cp' => 'flags/cp.svg',
        // 'cr' => 'flags/cr.svg',
        // 'cu' => 'flags/cu.svg',
        // 'cv' => 'flags/cv.svg',
        // 'cw' => 'flags/cw.svg',
        // 'cx' => 'flags/cx.svg',
        // 'cy' => 'flags/cy.svg',
        // 'cz' => 'flags/cz.svg',
        'de' => 'flags/de.svg',
        // 'dg' => 'flags/dg.svg',
        // 'dj' => 'flags/dj.svg',
        // 'dk' => 'flags/dk.svg',
        // 'dm' => 'flags/dm.svg',
        // 'do' => 'flags/do.svg',
        // 'dz' => 'flags/dz.svg',
        // 'eac' => 'flags/eac.svg',
        // 'ec' => 'flags/ec.svg',
        // 'ee' => 'flags/ee.svg',
        // 'eg' => 'flags/eg.svg',
        // 'eh' => 'flags/eh.svg',
        'en' => 'flags/gb.svg',
        // 'er' => 'flags/er.svg',
        // 'es-ct' => 'flags/es-ct.svg',
        // 'es-ga' => 'flags/es-ga.svg',
        // 'es-pv' => 'flags/es-pv.svg',
        // 'es' => 'flags/es.svg',
        // 'et' => 'flags/et.svg',
        // 'eu' => 'flags/eu.svg',
        // 'fi' => 'flags/fi.svg',
        // 'fj' => 'flags/fj.svg',
        // 'fk' => 'flags/fk.svg',
        // 'fm' => 'flags/fm.svg',
        // 'fo' => 'flags/fo.svg',
        'fr' => 'flags/fr.svg',
        // 'ga' => 'flags/ga.svg',
        // 'gb-eng' => 'flags/gb-eng.svg',
        // 'gb-nir' => 'flags/gb-nir.svg',
        // 'gb-sct' => 'flags/gb-sct.svg',
        // 'gb-wls' => 'flags/gb-wls.svg',
        'gb' => 'flags/gb.svg',
        // 'gd' => 'flags/gd.svg',
        'ge' => 'flags/ge.svg',
        // 'gf' => 'flags/gf.svg',
        // 'gg' => 'flags/gg.svg',
        // 'gh' => 'flags/gh.svg',
        // 'gi' => 'flags/gi.svg',
        // 'gl' => 'flags/gl.svg',
        // 'gm' => 'flags/gm.svg',
        // 'gn' => 'flags/gn.svg',
        // 'gp' => 'flags/gp.svg',
        // 'gq' => 'flags/gq.svg',
        // 'gr' => 'flags/gr.svg',
        // 'gs' => 'flags/gs.svg',
        // 'gt' => 'flags/gt.svg',
        // 'gu' => 'flags/gu.svg',
        // 'gw' => 'flags/gw.svg',
        // 'gy' => 'flags/gy.svg',
        // 'hk' => 'flags/hk.svg',
        // 'hm' => 'flags/hm.svg',
        // 'hn' => 'flags/hn.svg',
        // 'hr' => 'flags/hr.svg',
        // 'ht' => 'flags/ht.svg',
        // 'hu' => 'flags/hu.svg',
        // 'ic' => 'flags/ic.svg',
        // 'id' => 'flags/id.svg',
        // 'ie' => 'flags/ie.svg',
        // 'il' => 'flags/il.svg',
        // 'im' => 'flags/im.svg',
        // 'in' => 'flags/in.svg',
        // 'io' => 'flags/io.svg',
        // 'iq' => 'flags/iq.svg',
        // 'ir' => 'flags/ir.svg',
        // 'is' => 'flags/is.svg',
        // 'it' => 'flags/it.svg',
        // 'je' => 'flags/je.svg',
        // 'jm' => 'flags/jm.svg',
        // 'jo' => 'flags/jo.svg',
        // 'jp' => 'flags/jp.svg',
        // 'ke' => 'flags/ke.svg',
        // 'kg' => 'flags/kg.svg',
        // 'kh' => 'flags/kh.svg',
        // 'ki' => 'flags/ki.svg',
        // 'km' => 'flags/km.svg',
        // 'kn' => 'flags/kn.svg',
        // 'kp' => 'flags/kp.svg',
        // 'kr' => 'flags/kr.svg',
        // 'kw' => 'flags/kw.svg',
        // 'ky' => 'flags/ky.svg',
        // 'kz' => 'flags/kz.svg',
        // 'la' => 'flags/la.svg',
        // 'lb' => 'flags/lb.svg',
        // 'lc' => 'flags/lc.svg',
        // 'li' => 'flags/li.svg',
        // 'lk' => 'flags/lk.svg',
        // 'lr' => 'flags/lr.svg',
        // 'ls' => 'flags/ls.svg',
        // 'lt' => 'flags/lt.svg',
        // 'lu' => 'flags/lu.svg',
        // 'lv' => 'flags/lv.svg',
        // 'ly' => 'flags/ly.svg',
        // 'ma' => 'flags/ma.svg',
        // 'mc' => 'flags/mc.svg',
        // 'md' => 'flags/md.svg',
        // 'me' => 'flags/me.svg',
        // 'mf' => 'flags/mf.svg',
        // 'mg' => 'flags/mg.svg',
        // 'mh' => 'flags/mh.svg',
        // 'mk' => 'flags/mk.svg',
        // 'ml' => 'flags/ml.svg',
        // 'mm' => 'flags/mm.svg',
        // 'mn' => 'flags/mn.svg',
        // 'mo' => 'flags/mo.svg',
        // 'mp' => 'flags/mp.svg',
        // 'mq' => 'flags/mq.svg',
        // 'mr' => 'flags/mr.svg',
        // 'ms' => 'flags/ms.svg',
        // 'mt' => 'flags/mt.svg',
        // 'mu' => 'flags/mu.svg',
        // 'mv' => 'flags/mv.svg',
        // 'mw' => 'flags/mw.svg',
        // 'mx' => 'flags/mx.svg',
        // 'my' => 'flags/my.svg',
        // 'mz' => 'flags/mz.svg',
        // 'na' => 'flags/na.svg',
        // 'nc' => 'flags/nc.svg',
        // 'ne' => 'flags/ne.svg',
        // 'nf' => 'flags/nf.svg',
        // 'ng' => 'flags/ng.svg',
        // 'ni' => 'flags/ni.svg',
        'nl' => 'flags/nl.svg',
        // 'no' => 'flags/no.svg',
        // 'np' => 'flags/np.svg',
        // 'nr' => 'flags/nr.svg',
        // 'nu' => 'flags/nu.svg',
        // 'nz' => 'flags/nz.svg',
        // 'om' => 'flags/om.svg',
        // 'pa' => 'flags/pa.svg',
        // 'pc' => 'flags/pc.svg',
        // 'pe' => 'flags/pe.svg',
        // 'pf' => 'flags/pf.svg',
        // 'pg' => 'flags/pg.svg',
        // 'ph' => 'flags/ph.svg',
        // 'pk' => 'flags/pk.svg',
        // 'pl' => 'flags/pl.svg',
        // 'pm' => 'flags/pm.svg',
        // 'pn' => 'flags/pn.svg',
        // 'pr' => 'flags/pr.svg',
        // 'ps' => 'flags/ps.svg',
        // 'pt' => 'flags/pt.svg',
        // 'pw' => 'flags/pw.svg',
        // 'py' => 'flags/py.svg',
        // 'qa' => 'flags/qa.svg',
        // 're' => 'flags/re.svg',
        // 'ro' => 'flags/ro.svg',
        // 'rs' => 'flags/rs.svg',
        // 'ru' => 'flags/ru.svg',
        // 'rw' => 'flags/rw.svg',
        // 'sa' => 'flags/sa.svg',
        // 'sb' => 'flags/sb.svg',
        // 'sc' => 'flags/sc.svg',
        // 'sd' => 'flags/sd.svg',
        // 'se' => 'flags/se.svg',
        // 'sg' => 'flags/sg.svg',
        // 'sh-ac' => 'flags/sh-ac.svg',
        // 'sh-hl' => 'flags/sh-hl.svg',
        // 'sh-ta' => 'flags/sh-ta.svg',
        // 'zh' => 'flags/sh-ac.svg',
        // 'sh' => 'flags/sh.svg',
        // 'si' => 'flags/si.svg',
        // 'sj' => 'flags/sj.svg',
        // 'sk' => 'flags/sk.svg',
        // 'sl' => 'flags/sl.svg',
        // 'sm' => 'flags/sm.svg',
        // 'sn' => 'flags/sn.svg',
        // 'so' => 'flags/so.svg',
        // 'sr' => 'flags/sr.svg',
        // 'ss' => 'flags/ss.svg',
        // 'st' => 'flags/st.svg',
        // 'sv' => 'flags/sv.svg',
        // 'sx' => 'flags/sx.svg',
        // 'sy' => 'flags/sy.svg',
        // 'sz' => 'flags/sz.svg',
        // 'tc' => 'flags/tc.svg',
        // 'td' => 'flags/td.svg',
        // 'tf' => 'flags/tf.svg',
        // 'tg' => 'flags/tg.svg',
        // 'th' => 'flags/th.svg',
        // 'tj' => 'flags/tj.svg',
        // 'tk' => 'flags/tk.svg',
        // 'tl' => 'flags/tl.svg',
        // 'tm' => 'flags/tm.svg',
        // 'tn' => 'flags/tn.svg',
        // 'to' => 'flags/to.svg',
        // 'tr' => 'flags/tr.svg',
        // 'tt' => 'flags/tt.svg',
        // 'tv' => 'flags/tv.svg',
        // 'tw' => 'flags/tw.svg',
        // 'tz' => 'flags/tz.svg',
        // 'ua' => 'flags/ua.svg',
        // 'ug' => 'flags/ug.svg',
        // 'um' => 'flags/um.svg',
        // 'un' => 'flags/un.svg',
        'us' => 'flags/us.svg',
        // 'uy' => 'flags/uy.svg',
        // 'uz' => 'flags/uz.svg',
        // 'va' => 'flags/va.svg',
        // 'vc' => 'flags/vc.svg',
        // 've' => 'flags/ve.svg',
        // 'vg' => 'flags/vg.svg',
        // 'vi' => 'flags/vi.svg',
        // 'vn' => 'flags/vn.svg',
        // 'vu' => 'flags/vu.svg',
        // 'wf' => 'flags/wf.svg',
        // 'ws' => 'flags/ws.svg',
        // 'xk' => 'flags/xk.svg',
        // 'xx' => 'flags/xx.svg',
        // 'ye' => 'flags/ye.svg',
        // 'yt' => 'flags/yt.svg',
        // 'za' => 'flags/za.svg',
        // 'zm' => 'flags/zm.svg',
        // 'zw' => 'flags/zw.svg',
    ],

    /*
     |--------------------------------------------------------------------------
     | Encryption Key
     |--------------------------------------------------------------------------
     |
     | This key is utilized by Laravel's encryption services and should be set
     | to a random, 32 character string to ensure that all encrypted values
     | are secure. You should do this prior to deploying the application.
     |
     */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    /*
     |--------------------------------------------------------------------------
     | Maintenance Mode Driver
     |--------------------------------------------------------------------------
     |
     | These configuration options determine the driver used to determine and
     | manage Laravel's "maintenance mode" status. The "cache" driver will
     | allow maintenance mode to be controlled across multiple machines.
     |
     | Supported drivers: "file", "cache"
     |
     */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
