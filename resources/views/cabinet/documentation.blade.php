@extends('cabinet.layout.template')

@section('content')
    <div>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Inter, Arial, sans-serif;
                    background-color: #f6f7fb;
                    margin: 0;
                    padding: 40px;
                    color: #1f2937;
                }

                .container {
                    max-width: 900px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 12px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
                    padding: 40px;
                }

                h1 {
                    font-size: 28px;
                    margin-bottom: 10px;
                }

                h2 {
                    margin-top: 40px;
                    font-size: 22px;
                    border-bottom: 2px solid #e5e7eb;
                    padding-bottom: 6px;
                }

                h3 {
                    margin-top: 24px;
                    font-size: 18px;
                }

                p {
                    line-height: 1.6;
                    margin: 12px 0;
                    color: #374151;
                }

                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 999px;
                    background: #eef2ff;
                    color: #4338ca;
                    font-size: 12px;
                    font-weight: 600;
                }

                .box {
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 16px 0;
                }

                pre {
                    background: #0f172a;
                    color: #e5e7eb;
                    padding: 16px;
                    border-radius: 8px;
                    overflow-x: auto;
                    font-size: 14px;
                }

                code {
                    font-family: "JetBrains Mono", monospace;
                }

                .endpoint {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    margin: 12px 0;
                }

                .method {
                    font-weight: bold;
                    padding: 4px 10px;
                    border-radius: 6px;
                    font-size: 13px;
                }

                .get {
                    background: #dcfce7;
                    color: #166534;
                }

                .note {
                    background: #fff7ed;
                    border: 1px solid #fed7aa;
                    color: #9a3412;
                    padding: 14px;
                    border-radius: 8px;
                    margin-top: 16px;
                }

                footer {
                    margin-top: 40px;
                    font-size: 13px;
                    color: #6b7280;
                    text-align: center;
                }
            </style>

        <div class="container">

            <h1>Merchant API Documentation</h1>
            <p class="badge">API v1</p>

            <p>
                This document describes how to connect to the Merchant API,
                authenticate your requests, and perform your first test request.
            </p>

            <h2>1. Authentication</h2>

            <p>
                All API requests must be authenticated using an API token.
                Each merchant has a unique token generated in the dashboard.
            </p>

            <div class="box">
                <strong>Authorization method:</strong> Bearer Token
            </div>

            <h3>Authorization Header</h3>

            <pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>

            <div class="note">
                <strong>Security note:</strong><br>
                Never share your API token publicly or store it in client-side code.
            </div>

            <h2>2. Base URL</h2>

            <pre><code>https://your-domain.com/api</code></pre>

            <h2>3. Test Connection</h2>

            <p>
                To verify that your API token is valid, send a test request
                to the <strong>/check</strong> endpoint.
            </p>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/check</code>
            </div>

            <h3>Example Request (curl)</h3>

            <pre><code>curl -X GET https://your-domain.com/api/check \
-H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h3>Successful Response</h3>

            <pre><code>{
  "message": "Ok. Signature accepted."
}</code></pre>

            <h3>Error Responses</h3>

            <pre><code>// Missing token
{
  "error": "Unauthorized: token missing"
}

// Invalid token
{
  "error": "Unauthorized: invalid token"
}</code></pre>

            <h2>4. HTTP Status Codes</h2>

            <div class="box">
                <p><strong>200</strong> — Request successful</p>
                <p><strong>401</strong> — Unauthorized (missing or invalid token)</p>
                <p><strong>500</strong> — Internal server error</p>
            </div>

            <h2>5. Next Steps</h2>

            <p>
                Once authentication is successful, you can proceed with:
            </p>

            <ul>
                <li>Creating payments</li>
                <li>Receiving webhooks</li>
                <li>Querying transaction status</li>
            </ul>

    </div>
@endsection
