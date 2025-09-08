# Monitoring and Observability

## Monitoring Stack
- **Frontend Monitoring:** Browser Performance API, Google Analytics 4 for user behavior tracking
- **Backend Monitoring:** DigitalOcean App Platform Insights, PHP error_log integration
- **Error Tracking:** Custom error logging to system_logs table, Platform log aggregation
- **Performance Monitoring:** App Platform metrics (CPU, memory, response times), SQLite query profiling

## Key Metrics

**Frontend Metrics:**
- Core Web Vitals (LCP < 2.5s, FID < 100ms, CLS < 0.1)
- JavaScript errors and stack traces
- API response times from user perspective
- User interactions (filter usage, modal opens, tournament clicks)
- Korean language content rendering performance

**Backend Metrics:**
- Request rate (requests per minute)
- Error rate (percentage of failed requests)
- Response time (p50, p95, p99 percentiles)
- Database query performance (SQLite slow query log)
- Daily parser success/failure rates
- Cross-language parsing accuracy rates

**Korean Market Specific:**
- Latency from Korea (Seoul, Busan test locations)
- Korean character rendering performance
- osu! API integration success rates
- Tournament parsing accuracy for Korean posts

This comprehensive fullstack architecture provides a solid foundation for the Tourney Method platform, optimized for the Korean market while maintaining flexibility for future international expansion. The architecture balances simplicity with scalability, ensuring reliable tournament discovery for the osu! community.