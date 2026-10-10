<?php
namespace GeoBoost\Scan;

final class SiteScanner {

	public const OPTION = 'geoboost_site_scan';
	public const HEADER = 'X-GEOBoost-Scan';

	private const TOKEN_KEY  = 'geoboost_scan_token';
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
		$page = $this->fetch( $url, self::BROWSER_UA, 10, self::PAGE_CAP );
		if ( $page['status'] < 200 || $page['status'] >= 300 || '' === $page['body'] ) {
			return array(
				'url'        => $url,
				'scanned_at' => time(),
				'error'      => $page['status']
					/* translators: %d: HTTP status code */
					? sprintf( __( 'The page answered with HTTP %d, so there was nothing to scan.', 'geoboost' ), $page['status'] )
					: sprintf(
						/* translators: %s: error message */
						__( 'Your server couldn\'t load its own page (%s). Some hosts block requests a site makes to itself; Tools > Site Health reports this as a loopback problem.', 'geoboost' ),
						'' !== $page['error'] ? $page['error'] : __( 'no response', 'geoboost' )
					),
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

		$bots = array();
		foreach ( self::LIVE_BOTS as $name => $ua ) {
			$res    = $this->fetch( $page['final_url'], $ua, 6, 20000 );
			$bots[] = array(
				'name'   => $name,
				'status' => $res['status'],
				'error'  => $res['error'],
			);
		}

		$checker = new Checks();
		$checks  = $checker->build(
			array(
				'path'          => $path,
				'headers'       => $page['headers'],
				'facts'         => ( new PageParser() )->parse( $page['body'] ),
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

	private function looks_like_html( string $body ): bool {
		return 1 === preg_match( '/^\s*(<!doctype html|<html|<head|<body)/i', $body );
	}

	/**
	 * @return array{status:int,body:string,headers:array<string,string>,final_url:string,error:string}
	 */
	private function fetch( string $url, string $ua, int $timeout, int $cap ): array {
		if ( $this->fetcher ) {
			$res = ( $this->fetcher )( $url, $ua );
			return array(
				'status'    => (int) ( $res['status'] ?? 0 ),
				'body'      => (string) ( $res['body'] ?? '' ),
				'headers'   => (array) ( $res['headers'] ?? array() ),
				'final_url' => (string) ( $res['final_url'] ?? $url ),
				'error'     => (string) ( $res['error'] ?? '' ),
			);
		}

		$headers = array( 'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8' );
		if ( '' !== $this->token ) {
			$headers[ self::HEADER ] = $this->token;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => $timeout,
				'redirection'         => 5,
				'user-agent'          => $ua,
				'headers'             => $headers,
				'limit_response_size' => $cap,
				'sslverify'           => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'    => 0,
				'body'      => '',
				'headers'   => array(),
				'final_url' => $url,
				'error'     => $response->get_error_message(),
			);
		}

		$final = $url;
		if ( isset( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$final = (string) $response['http_response']->get_response_object()->url;
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
			'status'    => (int) wp_remote_retrieve_response_code( $response ),
			'body'      => (string) wp_remote_retrieve_body( $response ),
			'headers'   => $lower,
			'final_url' => $final,
			'error'     => '',
		);
	}
}
