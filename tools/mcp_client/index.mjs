import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { SSEClientTransport } from "@modelcontextprotocol/sdk/client/sse.js";
import fs from "fs";

async function main() {
  const url = "https://stitch.googleapis.com/mcp";
  const apiKey = "AQ.Ab8RN6KMFEdguFNwP_mgT3e-2Nh2B7wFf75N7UHXdhh91twP5g";
  
  console.log("Connecting to Google Stitch MCP server...");
  
  const transport = new SSEClientTransport(new URL(url), {
    requestInit: {
        headers: {
        "X-Goog-Api-Key": apiKey
        }
    }
  });

  const client = new Client({
    name: "Antigravity-Fetcher",
    version: "1.0.0"
  }, {
    capabilities: {
      resources: {},
      tools: {},
      prompts: {}
    }
  });

  await client.connect(transport);
  console.log("Connected successfully!");

  try {
      const resources = await client.listResources();
      console.log("\n--- Available Resources ---");
      console.log(JSON.stringify(resources, null, 2));

      if (resources.resources && resources.resources.length > 0) {
        for (const res of resources.resources) {
          console.log(`\nFetching resource: ${res.uri} (${res.name})...`);
          try {
            const data = await client.readResource({ uri: res.uri });
            const content = data.contents[0];
            let fileContent = content.text;
            if (content.mimeType === "application/json" && typeof content.text === "string") {
              // Parse and stringify for pretty print if it's json, otherwise keep as is
              try {
                fileContent = JSON.stringify(JSON.parse(content.text), null, 2);
              } catch(e) {}
            }

            const filename = `stitch_${res.name.replace(/[^a-z0-9.]/gi, '_').toLowerCase()}`;
            fs.writeFileSync(filename, fileContent || JSON.stringify(data, null, 2));
            console.log(`Saved as ${filename}`);
          } catch(e) {
            console.log(`Failed to fetch ${res.uri}:`, e.message);
          }
        }
      }
  } catch (e) {
      console.log("Error listing resources:", e.message);
  }

  try {
      const tools = await client.listTools();
      console.log("\n--- Available Tools ---");
      console.log(JSON.stringify(tools, null, 2));
  } catch (e) {
      console.log("Tools not supported or error:", e.message);
  }
  
  try {
      const prompts = await client.listPrompts();
      console.log("\n--- Available Prompts ---");
      console.log(JSON.stringify(prompts, null, 2));
  } catch (e) {
      console.log("Prompts not supported or error:", e.message);
  }

  console.log("\nDone!");
  process.exit(0);
}

main().catch(err => {
  console.error("Fatal Error:", err);
  process.exit(1);
});
