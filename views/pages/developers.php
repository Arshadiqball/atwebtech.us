<?php
$base = absolute_url('/api/v1');
?>
<article class="section">
  <div class="container article-wrap">
    <h1 class="h-page">atwebtech API</h1>
    <p class="article-meta">REST API and webhooks · Essentials and Team plans</p>
    <div class="prose" style="margin-top: 32px">
      <p>Schedule posts and read results from your own tools. Create an API key in the workspace under <b>Account menu → API &amp; webhooks</b>. Each key works for one workspace (client workspaces have their own keys).</p>

      <h2>Authentication</h2>
      <p>Send the key in the <code>Authorization</code> header. Requests and responses are JSON. Each key can make up to 120 requests a minute.</p>
      <pre><code>curl <?= e($base) ?>/channels \
  -H "Authorization: Bearer awt_your_key"</code></pre>

      <h2>Endpoints</h2>
      <table>
        <thead><tr><th>Method and path</th><th>What it does</th></tr></thead>
        <tbody>
          <tr><td><code>GET /api/v1/me</code></td><td>The workspace the key belongs to: id, name, time zone, plan.</td></tr>
          <tr><td><code>GET /api/v1/channels</code></td><td>Connected channels: <code>id</code>, <code>network</code>, <code>name</code>, <code>can_publish</code>, <code>paused</code>.</td></tr>
          <tr><td><code>GET /api/v1/posts</code></td><td>Latest posts. Filters: <code>status</code> (draft, planned, review, sent, failed), <code>channel_id</code>, <code>limit</code> (up to 100).</td></tr>
          <tr><td><code>GET /api/v1/posts/{id}</code></td><td>One post, with its status, link and any error.</td></tr>
          <tr><td><code>POST /api/v1/posts</code></td><td>Create a post (below).</td></tr>
          <tr><td><code>DELETE /api/v1/posts/{id}</code></td><td>Delete a post that hasn’t been published.</td></tr>
        </tbody>
      </table>

      <h2>Create a post</h2>
      <pre><code>curl -X POST <?= e($base) ?>/posts \
  -H "Authorization: Bearer awt_your_key" \
  -H "Content-Type: application/json" \
  -d '{"channel_id": 12, "text": "New menu this week!", "scheduled_at": "2026-12-01T09:00:00-05:00", "media_ids": [34, 35]}'</code></pre>
      <ul>
        <li><code>channel_id</code> (required): from <code>GET /channels</code>.</li>
        <li><code>text</code>: the post. Network limits apply (Bluesky 300, Threads 500, Instagram 2,200, LinkedIn 3,000 characters).</li>
        <li><code>scheduled_at</code>: an ISO 8601 time. Without it, or with <code>"draft": true</code>, the post is saved as a draft.</li>
        <li><code>media_ids</code>: files from the Media Library (the id is in each file’s address). Two or more images make a carousel. Instagram and Pinterest need a photo; YouTube needs one video and a <code>title</code>.</li>
      </ul>
      <p>Errors use HTTP status codes with a body like <code>{"error": {"code": "invalid_time", "message": "…"}}</code>.</p>

      <h2>Webhooks</h2>
      <p>Add an https endpoint under API &amp; webhooks and choose events:</p>
      <ul>
        <li><code>post.published</code>: post id, channel, network, text and the live link.</li>
        <li><code>post.failed</code>: the same, with the error.</li>
        <li><code>comment.received</code>: a new comment, reply or mention in Community.</li>
      </ul>
      <p>Each delivery is a JSON <code>POST</code> with <code>X-Atwebtech-Event</code>, <code>X-Atwebtech-Timestamp</code> and <code>X-Atwebtech-Signature: sha256=…</code>. The signature is an HMAC-SHA256 of <code>timestamp + "." + body</code> with your signing secret. Reply with a 2xx status; failed deliveries are retried up to 5 times, and an endpoint is paused after 20 failures in a row.</p>
      <pre><code>// PHP
$expected = hash_hmac('sha256', $_SERVER['HTTP_X_ATWEBTECH_TIMESTAMP'] . '.' . file_get_contents('php://input'), $secret);
$valid = hash_equals('sha256=' . $expected, $_SERVER['HTTP_X_ATWEBTECH_SIGNATURE']);</code></pre>

      <h2>Zapier, Make and n8n</h2>
      <p>Use <b>Webhooks by Zapier → Catch Hook</b> (or Make’s and n8n’s webhook trigger) as the endpoint for atwebtech events, and their HTTP step with your API key to create posts.</p>
    </div>
  </div>
</article>
