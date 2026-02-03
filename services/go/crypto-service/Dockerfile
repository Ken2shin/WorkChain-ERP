FROM golang:1.23-alpine AS builder

WORKDIR /build

# Install dependencies
RUN apk add --no-cache git ca-certificates

# Copy go.mod and go.sum
COPY go.mod go.sum ./

# Download modules
RUN go mod download

# Copy source
COPY . .

# Build binary
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o crypto-service main.go

# Final stage
FROM alpine:3.20

WORKDIR /app

# Install ca-certificates
RUN apk add --no-cache ca-certificates

# Copy binary from builder
COPY --from=builder /build/crypto-service .

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
  CMD wget --quiet --tries=1 --spider http://localhost:3000/health || exit 1

# Run
ENTRYPOINT ["./crypto-service"]
