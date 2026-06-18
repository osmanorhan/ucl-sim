# syntax=docker/dockerfile:1

FROM node:24-alpine

RUN corepack enable

WORKDIR /web

COPY apps/web/package.json apps/web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY apps/web/ ./

EXPOSE 5173

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
