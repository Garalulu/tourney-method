# External APIs

## osu! Forum API
- **Purpose:** Retrieve tournament forum posts for automated parsing
- **Documentation:** https://osu.ppy.sh/docs/index.html#forum
- **Base URL(s):** https://osu.ppy.sh/api/v2/
- **Authentication:** OAuth 2.0 client credentials
- **Rate Limits:** 1000 requests per hour

**Key Endpoints Used:**
- `GET /forum/topics` - List tournament topics
- `GET /forum/topics/{id}` - Get specific tournament post content

**Integration Notes:** Daily parser respects rate limits, implements exponential backoff for failures, caches responses to minimize API calls

## osu! OAuth 2.0 API
- **Purpose:** Admin authentication and user verification
- **Documentation:** https://osu.ppy.sh/docs/index.html#authentication
- **Base URL(s):** https://osu.ppy.sh/oauth/
- **Authentication:** OAuth 2.0 authorization code flow
- **Rate Limits:** Standard OAuth limits apply

**Key Endpoints Used:**
- `POST /oauth/authorize` - Authorization endpoint
- `POST /oauth/token` - Token exchange endpoint
- `GET /api/v2/me` - Get authenticated user details

**Integration Notes:** Secure token storage, automatic token refresh, admin whitelist verification against osu! user IDs
