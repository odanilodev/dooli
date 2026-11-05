FROM node:20-alpine AS build

ARG VITE_API_URL=/api/v1
ARG VITE_COMPANY_ID=
ARG VITE_PLATFORM_DOMAINS=
ARG VITE_MAX_UPLOAD_MB=20

ENV VITE_API_URL=$VITE_API_URL \
    VITE_COMPANY_ID=$VITE_COMPANY_ID \
    VITE_PLATFORM_DOMAINS=$VITE_PLATFORM_DOMAINS \
    VITE_MAX_UPLOAD_MB=$VITE_MAX_UPLOAD_MB

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM nginx:1.27-alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
