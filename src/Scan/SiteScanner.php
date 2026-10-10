<?php
namespace GeoBoost\Scan;

final class SiteScanner {

	public const OPTION = 'geoboost_site_scan';
	public const HEADER = 'X-GEOBoost-Scan';

	private const TOKEN_KEY  = 'geoboost_scan_token';
	private const CHALLENGE  = '/cf-chl|challenge-platform|just a moment\.\.\.|captcha|access denied|attention required|are you a robot|verify you are human|password-protected|opening soon/i';
	private const PAGE_CAP   = 1500000;
	private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 GEOBoostSiteScan';
	private const LIVE_BOTS  = array(
		'GPTBot'        => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot',
		'ClaudeBot'     => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
		'PerplexityBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
	);

	/** @var callable|null */
	private $fetcher = null;

	private string $token = '';

	/** @var array{host:string,port:int|null} */
	private array $scope = array(
		'host' => '',
		'port' => null,
	);

	public function set_fetcher( callable $fetcher ): void {
		$this->fetcher = $fetcher;
	}

	/**
	 * Accepts a path or a full URL and returns an absolute URL on the same
	 * host as $home, or null if it points anywhere else.
	 */
	public static function normalize_target( string $input, string $home ): ?string {
		$input = trim( $input );
		$home  = wp_parse_url( $home );
		if ( empty( $home['host'] ) ) {
			return null;
		}
		$origin = ( $home['scheme'] ?? 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		if ( '' === $input ) {
			$input = $origin . ( $home['path'] ?? '/' );
		} elseif ( '/' === $input[0] && ( ! isset( $input[1] ) || '/' !== $input[1] ) ) {
			$input = $origin . $input;
		}

		$target = wp_parse_url( $input );
		if (
			empty( $target['host'] )
			|| ! in_array( strtolower( $target['scheme'] ?? '' ), array( 'http', 'https' ), true )
			|| isset( $target['user'] ) || isset( $target['pass'] )
			|| strtolower( $target['host'] ) !== strtolower( $home['host'] )
			|| ( $target['port'] ?? null ) !== ( $home['port'] ?? null )
		) {
			return null;
		}

		return strtolower( $target['scheme'] ) . '://' . strtolower( $target['host'] )
			. ( isset( $target['port'] ) ? ':' . $target['port'] : '' )
			. ( $target['path'] ?? '/' )
			. ( isset( $target['query'] ) ? '?' . $target['query'] : '' );
	}

	/**
	 * Marks the scan's own requests so BotTracker doesn't log them as crawls.
	 */
	public static function is_scan_request( string $header_value ): bool {
		$token = get_transient( self::TOKEN_KEY );
		return is_string( $token ) && '' !== $token && hash_equals( $token, $header_value );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function run( string $url, bool $blog_public ): array {
		$this->token = wp_generate_password( 32, false );
		set_transient( self::TOKEN_KEY, $this->token, 5 * MINUTE_IN_SECONDS );
		try {
			return $this->scan( $url, $blog_public );
		} finally {
			delete_transient( self::TOKEN_KEY );
			$this->token = '';
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function scan( string $url, bool $blog_public ): array {
		$start       = wp_parse_url( $url );
		$this->scope = array(
			'host' => strtolower( (string) ( $start['host'] ?? '' ) ),
			'port' => $start['port'] ?? null,
		);

		$page = $this->fetch( $url, self::BROWSER_UA, 10, self::PAGE_CAP );
		if ( $page['status'] < 200 || $page['status'] >= 300 || '' === $page['body'] ) {
			return array(
				'url'        => $url,
				'scanned_at' => time(),
				'error'      => $page['status'] >= 300 && $page['status'] < 400 && '' !== $page['error']
					? $page['error']
					: ( $page['status']
					/* translators: %d: HTTP status code */
					? sprintf( __( 'The page answered with HTTP %d, so there was nothing to scan.', 'geoboost' ), $page['status'] )
					: sprintf(
						/* translators: %s: error message */
						__( 'Your server couldn\'t load its own page (%s). Some hosts block requests a site makes to itself; Tools > Site Health reports this as a loopback problem.', 'geoboost' ),
						'' !== $page['error'] ? $page['error'] : __( 'no response', 'geoboost' )
					) ),
			);
		}

		$final  = wp_parse_url( $page['final_url'] );
		$origin = ( $final['scheme'] ?? 'https' ) . '://' . ( $final['host'] ?? '' ) . ( isset( $final['port'] ) ? ':' . $final['port'] : '' );
		$path   = ( $final['path'] ?? '/' ) . ( isset( $final['query'] ) ? '?' . $final['query'] : '' );

		$robots_res = $this->fetch( $origin . '/robots.txt', self::BROWSER_UA, 5, 300000 );
		$robots     = 200 === $robots_res['status'] && ! $this->looks_like_html( $robots_res['body'] )
			? new RobotsTxt( $robots_res['body'] )
			: null;

		$llms     = $this->fetch( $origin . '/llms.txt', self::BROWSER_UA, 5, 300000 );
		$llms_len = 200 === $llms['status'] && strlen( trim( $llms['body'] ) ) > 20 && ! $this->looks_like_html( $llms['body'] )
			? strlen( $llms['body'] )
			: 0;

		$facts = ( new PageParser() )->parse( $page['body'] );
		$bots  = array();
		foreach ( self::LIVE_BOTS as $name => $ua ) {
			// Same cap as the page, or a truncated copy reads as a thin page.
			$res    = $this->fetch( $page['final_url'], $ua, 8, self::PAGE_CAP );
			$bots[] = array(
				'name'       => $name,
				'status'     => $res['status'],
				'error'      => $res['error'],
				'challenged' => self::is_challenged( $page, (int) $facts['word_count'], $res ),
			);
		}

		$checker = new Checks();
		$checks  = $checker->build(
			array(
				'path'          => $path,
				'headers'       => $page['headers'],
				'facts'         => $facts,
				'robots'        => $robots,
				'robots_status' => $robots_res['status'],
				'blog_public'   => $blog_public,
				'bots'          => $bots,
				'sitemap'       => $this->find_sitemap( $origin, (string) ( $final['host'] ?? '' ), null !== $robots ? $robots->sitemaps() : array() ),
				'llms_length'   => $llms_len,
			)
		);
		$score   = $checker->score( $checks );

		return array(
			'url'        => $url,
			'final_url'  => $page['final_url'],
			'scanned_at' => time(),
			'score'      => $score['score'],
			'categories' => $score['categories'],
			'checks'     => $checks,
		);
	}

	/**
	 * @param array<int,string> $declared
	 */
	private function find_sitemap( string $origin, string $host, array $declared ): ?string {
		$candidates = array();
		foreach ( array_slice( $declared, 0, 3 ) as $url ) {
			// Only follow sitemaps on this site; robots.txt can point anywhere.
			if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === strtolower( $host ) ) {
				$candidates[] = $url;
			}
		}
		$candidates[] = $origin . '/sitemap.xml';
		$candidates[] = $origin . '/sitemap_index.xml';
		$candidates[] = $origin . '/wp-sitemap.xml';

		foreach ( array_unique( $candidates ) as $url ) {
			$res = $this->fetch( $url, self::BROWSER_UA, 5, 200000 );
			if ( 200 === $res['status'] && preg_match( '/<(urlset|sitemapindex)\b/i', $res['body'] ) ) {
				return $url;
			}
		}
		return null;
	}

	/**
	 * A 200 can still be a challenge, password or consent page served only to bots.
	 *
	 * @param array{body:string,final_url:string} $page
	 * @param array{status:int,body:string,final_url:string} $bot
	 */
	public static function is_challenged( array $page, int $page_words, array $bot ): bool {
		if ( $bot['status'] < 200 || $bot['status'] >= 400 ) {
			return false;
		}
		$page_url = wp_parse_url( $page['final_url'] );
		$bot_url  = wp_parse_url( $bot['final_url'] );
		if ( strtolower( $bot_url['host'] ?? '' ) !== strtolower( $page_url['host'] ?? '' ) ) {
			return true;
		}
		if ( ( $bot_url['path'] ?? '/' ) !== ( $page_url['path'] ?? '/' ) && preg_match( '/login|password|consent|challenge|captcha/i', $bot['final_url'] ) ) {
			return true;
		}
		if ( preg_match( self::CHALLENGE, substr( $bot['body'], 0, 50000 ) ) && ! preg_match( self::CHALLENGE, substr( $page['body'], 0, 50000 ) ) ) {
			return true;
		}
		if ( $page_words < 200 ) {
			return false;
		}
		$bot_words = (int) ( new PageParser() )->parse( $bot['body'] )['word_count'];
		return $bot_words < $page_words * 0.3;
	}

	private function looks_like_html( string $body ): bool {
		return 1 === preg_match( '/^\s*(<!doctype html|<html|<head|<body)/i', $body );
	}

	/**
	 * Follows up to five redirects by hand, refusing any hop that leaves the
	 * scanned site's host and port or isn't http(s).
	 *
	 * @return array{status:int,body:string,headers:array<string,string>,final_url:string,error:string}
	 */
	private function fetch( string $url, string $ua, int $timeout, int $cap ): array {
		for ( $hop = 0; ; $hop++ ) {
			$res      = $this->request( $url, $ua, $timeout, $cap );
			$location = $res['headers']['location'] ?? '';
			if ( $res['status'] < 300 || $res['status'] >= 400 || '' === $location ) {
				return $res + array( 'final_url' => $url );
			}

			$next = self::resolve_location( $location, $url );
			if ( $hop >= 5 ) {
				$res['error'] = __( 'Too many redirects.', 'geoboost' );
			} elseif ( ! $this->in_scope( $next ) ) {
				/* translators: %s: redirect target URL */
				$res['error'] = sprintf( __( 'Redirect to %s not followed because it leaves this site.', 'geoboost' ), $next );
			} else {
				$url = $next;
				continue;
			}
			$res['body'] = '';
			return $res + array( 'final_url' => $next );
		}
	}

	private function in_scope( string $url ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
			&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] )
			&& strtolower( $parts['host'] ?? '' ) === $this->scope['host']
			&& ( $parts['port'] ?? null ) === $this->scope['port'];
	}

	private static function resolve_location( string $location, string $base ): string {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $location ) ) {
			return $location;
		}
		$b      = wp_parse_url( $base );
		$scheme = $b['scheme'] ?? 'https';
		if ( 0 === strpos( $location, '//' ) ) {
			return $scheme . ':' . $location;
		}
		$origin = $scheme . '://' . ( $b['host'] ?? '' ) . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
		if ( '' !== $location && '/' === $location[0] ) {
			return $origin . $location;
		}
		$dir = (string) preg_replace( '#/[^/]*$#', '/', $b['path'] ?? '/' );
		return $origin . ( '' !== $dir ? $dir : '/' ) . $location;
	}

	/**
	 * One request, no redirects.
	 *
	 * @return array{status:int,body:string,headers:array<string,string>,error:string}
	 */
	private function request( string $url, string $ua, int $timeout, int $cap ): array {
		if ( $this->fetcher ) {
			$res = ( $this->fetcher )( $url, $ua, $cap );
			return array(
				'status'  => (int) ( $res['status'] ?? 0 ),
				'body'    => (string) ( $res['body'] ?? '' ),
				'headers' => array_change_key_case( (array) ( $res['headers'] ?? array() ) ),
				'error'   => (string) ( $res['error'] ?? '' ),
			);
		}

		$headers = array( 'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8' );
		if ( '' !== $this->token ) {
			$headers[ self::HEADER ] = $this->token;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => $timeout,
				'redirection'         => 0,
				'user-agent'          => $ua,
				'headers'             => $headers,
				'limit_response_size' => $cap,
				'sslverify'           => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 0,
				'body'    => '',
				'headers' => array(),
				'error'   => $response->get_error_message(),
			);
		}

		$raw = wp_remote_retrieve_headers( $response );
		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			$raw = $raw->getAll();
		}
		$lower = array();
		foreach ( (array) $raw as $k => $v ) {
			$lower[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $lower,
			'error'   => '',
		);
	}
}
