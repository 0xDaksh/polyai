FROM node:lts-bullseye-slim as builder

WORKDIR /usr/src/app
COPY package*.json ./

RUN npm i -g bun
RUN bun install

COPY . .

RUN bun run build

FROM node:lts-bullseye-slim

RUN npm i -g bun


ENV NODE_ENV production
ENV NEW_RELIC_NO_CONFIG_FILE=true
ENV NEW_RELIC_DISTRIBUTED_TRACING_ENABLED=true
ENV NEW_RELIC_LOG=stdout

# Create app directory
WORKDIR /usr/src/app

# Install app dependencies
COPY package*.json ./

COPY --from=builder /usr/src/app/dist ./dist
COPY --from=builder /usr/src/app/prisma ./prisma
COPY --from=builder /usr/src/app/node_modules ./node_modules

CMD sleep 3 && bun ./dist/index.js