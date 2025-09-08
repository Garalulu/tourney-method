# API Specification

## REST API Specification

```yaml
openapi: 3.0.0
info:
  title: Tourney Method API
  version: 1.0.0
  description: API for osu! tournament discovery and management
servers:
  - url: https://tourneymethod.com/api
    description: Production server (Korean-optimized)
  - url: http://localhost:8000/api
    description: Development server

components:
  schemas:
    Tournament:
      type: object
      properties:
        id:
          type: integer
          example: 123
        topic_id:
          type: integer
          example: 1867432
        title:
          type: string
          example: "Korean Spring Tournament 2025"
        host:
          type: string
          example: "TournamentHost"
        mode:
          type: string
          enum: [Standard, Taiko, Catch, Mania]
          example: "Standard"
        banner_url:
          type: string
          format: uri
          example: "https://example.com/banner.jpg"
        rank_range:
          type: string
          example: "1k-5k"
        registration_status:
          type: string
          enum: [Open, Closed, Ongoing]
          example: "Open"
        registration_link:
          type: string
          format: uri
        discord_link:
          type: string
          format: uri
        created_at:
          type: string
          format: date-time
          example: "2025-09-05T14:30:00+09:00"
        language_detected:
          type: string
          example: "ko"
    
    ErrorResponse:
      type: object
      properties:
        error:
          type: object
          properties:
            code:
              type: string
              example: "TOURNAMENT_NOT_FOUND"
            message:
              type: string
              example: "Tournament not found"
            timestamp:
              type: string
              format: date-time
            request_id:
              type: string
              example: "req_123456789"

paths:
  /tournaments:
    get:
      summary: List approved tournaments
      parameters:
        - name: mode
          in: query
          schema:
            type: string
            enum: [Standard, Taiko, Catch, Mania]
        - name: rank_range
          in: query
          schema:
            type: string
        - name: status
          in: query
          schema:
            type: string
            enum: [Open, Closed, Ongoing]
        - name: limit
          in: query
          schema:
            type: integer
            default: 10
            maximum: 50
        - name: offset
          in: query
          schema:
            type: integer
            default: 0
      responses:
        '200':
          description: List of tournaments
          content:
            application/json:
              schema:
                type: object
                properties:
                  tournaments:
                    type: array
                    items:
                      $ref: '#/components/schemas/Tournament'
                  total:
                    type: integer
                  limit:
                    type: integer
                  offset:
                    type: integer
  
  /tournaments/{id}:
    get:
      summary: Get tournament details
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Tournament details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Tournament'
        '404':
          description: Tournament not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /admin/tournaments/pending:
    get:
      summary: List tournaments pending review (Admin only)
      security:
        - osu_oauth: []
      responses:
        '200':
          description: Pending tournaments
          content:
            application/json:
              schema:
                type: array
                items:
                  $ref: '#/components/schemas/Tournament'
        '401':
          description: Unauthorized

  /admin/tournaments/{id}/approve:
    post:
      summary: Approve tournament (Admin only)
      security:
        - osu_oauth: []
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Tournament approved
        '401':
          description: Unauthorized
        '404':
          description: Tournament not found

securitySchemes:
  osu_oauth:
    type: oauth2
    flows:
      authorizationCode:
        authorizationUrl: https://osu.ppy.sh/oauth/authorize
        tokenUrl: https://osu.ppy.sh/oauth/token
        scopes:
          identify: Read user identification
```

**Korean Considerations:**
- All timestamps returned in KST timezone (+09:00)
- UTF-8 encoding enforced for all text fields
- Error messages support Korean characters
- API documentation available in both English and Korean
