# Technical Assumptions

*   **Repository Structure: Monorepo**
    *   All project code, including backend (PHP), frontend (CSS/JS), and database schema, will be contained within a single Git repository.
*   **Service Architecture: Monolith**
    *   The application will be a traditional, self-contained monolith. All functionality will be deployed as a single unit. This is the simplest and most appropriate architecture for the project's scale.
*   **Testing Requirements: Unit + Integration Testing**
    *   **Unit Tests:** Critical, isolated pieces of logic (like the BWS calculation) should be covered by unit tests to ensure their correctness.
    *   **Integration Tests:** The data pipeline—specifically the parser's ability to correctly save information to the database—should be covered by integration tests. This is to ensure the most critical part of the system works as expected.
    *   *Note:* A full end-to-end (E2E) automated testing suite is considered out of scope for the MVP due to its complexity.
*   **Additional Technical Assumptions:**
    *   The technology stack is fixed as **Vanilla PHP, jQuery, and SQLite**.
    *   The application must be designed to run efficiently on a **low-resource DigitalOcean server**.
    *   The security requirements (prepared statements, output escaping, anti-CSRF tokens) are **non-negotiable** and must be implemented from the beginning.

---
