FROM rust:1.76-alpine AS builder

WORKDIR /build

# Install dependencies
RUN apk add --no-cache musl-dev

# Copy Cargo files
COPY Cargo.* ./

# Copy source
COPY src ./src

# Build release
RUN cargo build --release

# Final stage
FROM alpine:3.20

WORKDIR /app

# Install ca-certificates
RUN apk add --no-cache ca-certificates

# Copy binary
COPY --from=builder /build/target/release/anomaly-detector .

# Expose port
EXPOSE 3001

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
  CMD wget --quiet --tries=1 --spider http://localhost:3001/health || exit 1

# Run
ENTRYPOINT ["./anomaly-detector"]
